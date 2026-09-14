<?php

/**
 * @package     WmtHoneypot
 * @subpackage  System.wmthoneypot
 *
 * Anti-spam senza captcha per i moduli di contatto del core Joomla:
 *  - un campo "esca" fuori schermo, che i bot compilano e le persone no;
 *  - un istante di apertura firmato con il segreto del sito, per scartare gli invii
 *    troppo rapidi (tipici dei bot) e quelli replicati a distanza di giorni.
 *
 * Nessuna chiamata a servizi esterni, nessun cookie, nessun dato personale in più.
 *
 * @copyright   (C) 2026 WebMarkeThink
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wmt\Plugin\System\WmtHoneypot\Extension;

use Joomla\CMS\Event\Contact\ValidateContactEvent;
use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class WmtHoneypot extends CMSPlugin implements SubscriberInterface
{
    /**
     * Nome del campo con l'istante di apertura, firmato.
     */
    private const STAMP = 'wmt_apertura';

    /**
     * Nome di ripiego del campo esca, se il parametro è vuoto o non valido.
     */
    private const DEFAULT_FIELD = 'wmt_conferma';

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepareForm' => 'onContentPrepareForm',
            'onValidateContact'    => 'onValidateContact',
        ];
    }

    /**
     * Aggiunge i due campi ai moduli configurati.
     */
    public function onContentPrepareForm(PrepareFormEvent $event): void
    {
        $form = $event->getForm();
        $app  = $this->getApplication();

        if (!$app->isClient('site') || !\in_array($form->getName(), $this->forms(), true)) {
            return;
        }

        $this->loadLanguage();

        $field = $this->fieldName();

        $form->load(
            '<form><fieldset name="default">'
            . '<field name="' . $field . '" type="text" autocomplete="off"'
            . ' label="PLG_SYSTEM_WMTHONEYPOT_FIELD_LABEL"'
            . ' description="PLG_SYSTEM_WMTHONEYPOT_FIELD_DESC"'
            . ' class="wmt-hp-input" labelclass="wmt-hp-label" />'
            . '<field name="' . self::STAMP . '" type="hidden" />'
            . '</fieldset></form>'
        );

        $form->setValue(self::STAMP, null, $this->makeStamp());

        // Il campo resta nel DOM ma fuori dallo schermo: chi usa uno screen reader lo incontra
        // insieme all'etichetta, che dice esplicitamente di lasciarlo vuoto.
        $document = $app->getDocument();

        if ($document) {
            $document->addStyleDeclaration(
                '.wmt-hp-input,label.wmt-hp-label,'
                . 'input[name="jform[' . $field . ']"],label[for="jform_' . $field . '"]'
                . '{position:absolute!important;left:-10000px!important;width:1px!important;'
                . 'height:1px!important;overflow:hidden!important;}'
                . '.control-group:has(input[name="jform[' . $field . ']"])'
                . '{position:absolute!important;width:1px;height:1px;overflow:hidden;'
                . 'clip:rect(0 0 0 0);white-space:nowrap;}'
            );
        }
    }

    /**
     * Blocca l'invio del modulo Contatti se l'esca è stata compilata o se i tempi non tornano.
     */
    public function onValidateContact(ValidateContactEvent $event): void
    {
        $data  = $event->getData();
        $field = $this->fieldName();

        // Modulo servito prima dell'installazione del plugin (o pagina in cache):
        // non blocchiamo nulla, altrimenti si perderebbero messaggi veri.
        if (!\array_key_exists($field, $data) && !\array_key_exists(self::STAMP, $data)) {
            return;
        }

        $this->loadLanguage();

        $reason = $this->rejectionReason($data, $field);

        if ($reason === null) {
            return;
        }

        if ((int) $this->params->get('log', 1) === 1) {
            $this->log($reason);
        }

        $message = trim((string) $this->params->get('message', ''));

        $event->addResult(new \RuntimeException($message !== '' ? $message : Text::_('PLG_SYSTEM_WMTHONEYPOT_REJECTED')));
    }

    /**
     * @return string|null  Motivo del rifiuto, null se l'invio è legittimo.
     */
    private function rejectionReason(array $data, string $field): ?string
    {
        if (trim((string) ($data[$field] ?? '')) !== '') {
            return 'campo esca compilato';
        }

        $stamp = (string) ($data[self::STAMP] ?? '');

        if (!str_contains($stamp, '.')) {
            return 'istante di apertura assente';
        }

        [$openedAt, $signature] = explode('.', $stamp, 2);

        if (!hash_equals($this->sign($openedAt), $signature)) {
            return 'firma dell’istante di apertura non valida';
        }

        $elapsed  = time() - (int) $openedAt;
        $minimum  = (int) $this->params->get('min_seconds', 4);
        $maxHours = (int) $this->params->get('max_hours', 24);

        if ($elapsed < $minimum) {
            return 'modulo inviato dopo soli ' . max($elapsed, 0) . ' secondi';
        }

        if ($elapsed > $maxHours * 3600) {
            return 'modulo aperto più di ' . $maxHours . ' ore prima';
        }

        return null;
    }

    /**
     * Moduli su cui intervenire, uno per riga nei parametri.
     *
     * @return string[]
     */
    private function forms(): array
    {
        $raw   = (string) $this->params->get('forms', 'com_contact.contact');
        $forms = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw) ?: []));

        return $forms ?: ['com_contact.contact'];
    }

    /**
     * Nome del campo esca: cambiarlo rende inefficaci i bot tarati sul nome predefinito.
     */
    private function fieldName(): string
    {
        $name = strtolower(trim((string) $this->params->get('field_name', self::DEFAULT_FIELD)));
        $name = preg_replace('/[^a-z0-9_]/', '', $name) ?? '';

        return $name !== '' ? $name : self::DEFAULT_FIELD;
    }

    private function makeStamp(): string
    {
        $now = (string) time();

        return $now . '.' . $this->sign($now);
    }

    private function sign(string $value): string
    {
        return hash_hmac('sha256', $value, (string) $this->getApplication()->get('secret'));
    }

    private function log(string $reason): void
    {
        Log::addLogger(
            ['text_file' => 'wmthoneypot.log.php'],
            Log::ALL,
            ['plg_system_wmthoneypot']
        );

        Log::add('Invio bloccato: ' . $reason, Log::INFO, 'plg_system_wmthoneypot');
    }
}
