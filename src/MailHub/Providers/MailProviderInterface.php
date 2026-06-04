<?php
/**
 * SAGE Mail Hub — Mail Provider Interface
 * src/MailHub/Providers/MailProviderInterface.php
 *
 * Contract that every delivery driver must implement.
 * Swap Brevo for SMTP / Mailchimp / Postfix by implementing this.
 */

namespace App\MailHub\Providers;

interface MailProviderInterface
{
    /**
     * Send a single email message.
     *
     * @param  array $message {
     *   to_email:    string,
     *   to_name:     string|null,
     *   from_email:  string,
     *   from_name:   string,
     *   reply_to:    string|null,
     *   subject:     string,
     *   html:        string,
     *   text:        string|null,
     *   headers:     array<string,string>   (optional extra headers)
     * }
     * @return array {
     *   success:    bool,
     *   message_id: string|null,   (provider-assigned message ID on success)
     *   error:      string|null,
     *   raw:        mixed          (full provider response for debugging)
     * }
     */
    public function send(array $message): array;

    /**
     * Send a batch of messages in one API call when the provider supports it.
     * Implementations that don't support bulk can loop over send().
     *
     * @param  array[] $messages   Array of $message arrays (same shape as send())
     * @return array[]             Array of result arrays (same shape as send() return)
     */
    public function sendBatch(array $messages): array;

    /**
     * Return the driver key used in mail_hub_providers.driver.
     */
    public function getDriverKey(): string;

    /**
     * Validate provider config; return array of error strings (empty = valid).
     */
    public function validateConfig(array $config): array;
}
