<?php

namespace App\Service\Mail;

interface MailServiceInterface
{
    /**
     * Send an email
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $htmlContent HTML content of the email
     * @param string|null $textContent Plain text content (optional)
     * @param string|null $fromEmail Sender email (optional, uses default if not provided)
     * @param string|null $fromName Sender name (optional)
     */
    public function send(
        string $to,
        string $subject,
        string $htmlContent,
        ?string $textContent = null,
        ?string $fromEmail = null,
        ?string $fromName = null,
    ): void;
}