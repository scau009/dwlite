<?php

namespace App\Service\Mail;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Mail service implementation using Symfony Mailer.
 * Suitable for development (MailHog) and other SMTP-based transports.
 */
class SymfonyMailerService implements MailServiceInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail,
        private string $fromName,
        private LoggerInterface $logger,
    ) {
    }

    public function send(
        string $to,
        string $subject,
        string $htmlContent,
        ?string $textContent = null,
        ?string $fromEmail = null,
        ?string $fromName = null,
    ): void {
        $senderEmail = $fromEmail ?? $this->fromEmail;
        $senderName = $fromName ?? $this->fromName;

        $email = (new Email())
            ->from($this->formatEmailAddress($senderEmail, $senderName))
            ->to($to)
            ->subject($subject)
            ->html($htmlContent);

        if ($textContent !== null) {
            $email->text($textContent);
        }

        try {
            $this->mailer->send($email);

            $this->logger->info('Email sent successfully via Symfony Mailer', [
                'to' => $to,
                'subject' => $subject,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send email via Symfony Mailer', [
                'to' => $to,
                'subject' => $subject,
                'errorMessage' => $e->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('Failed to send email: %s', $e->getMessage()), 0, $e);
        }
    }

    public function sendWithTemplate(
        string $to,
        string $subject,
        int $templateId,
        array $templateData = [],
        ?string $fromEmail = null,
        ?string $fromName = null,
    ): void {
        // For Symfony Mailer, we don't support template IDs.
        // Instead, we'll send a simple HTML email with the template data as content.
        // In development, this is sufficient for testing the email flow.

        $htmlContent = $this->buildTemplateHtml($templateId, $templateData);

        $this->send($to, $subject, $htmlContent, null, $fromEmail, $fromName);

        $this->logger->info('Template email sent via Symfony Mailer (template rendered locally)', [
            'to' => $to,
            'templateId' => $templateId,
        ]);
    }

    private function formatEmailAddress(string $email, string $name): string
    {
        if (empty($name)) {
            return $email;
        }

        return sprintf('%s <%s>', $name, $email);
    }

    /**
     * Build a simple HTML representation of template data for development testing.
     *
     * @param int $templateId
     * @param array<string, string> $templateData
     * @return string
     */
    private function buildTemplateHtml(int $templateId, array $templateData): string
    {
        $dataHtml = '';
        $buttonHtml = '';

        foreach ($templateData as $key => $value) {
            // Detect URL fields and create clickable buttons
            if ($this->isUrl($value)) {
                $buttonHtml .= sprintf(
                    '<a href="%s" style="display: inline-block; background-color: #4A90D9; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 10px 5px;">%s</a>',
                    htmlspecialchars($value),
                    htmlspecialchars(ucfirst(str_replace('Url', '', $key)))
                );
                $dataHtml .= sprintf(
                    '<li><strong>%s:</strong> <a href="%s" style="color: #4A90D9; word-break: break-all;">%s</a></li>',
                    htmlspecialchars($key),
                    htmlspecialchars($value),
                    htmlspecialchars($value)
                );
            } else {
                $dataHtml .= sprintf('<li><strong>%s:</strong> %s</li>', htmlspecialchars($key), htmlspecialchars($value));
            }
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #4A90D9; color: white; padding: 20px; border-radius: 5px 5px 0 0; text-align: center; }
        .dev-notice { background: #fff3cd; border: 1px solid #ffc107; padding: 10px; font-size: 12px; }
        .content { padding: 20px; background: #f9f9f9; }
        .buttons { text-align: center; margin: 20px 0; }
        ul { list-style: none; padding: 0; }
        li { margin: 10px 0; padding: 10px; background: white; border-radius: 3px; border: 1px solid #eee; }
        .footer { text-align: center; padding: 15px; font-size: 12px; color: #666; background: #f0f0f0; border-radius: 0 0 5px 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>DWLite</h2>
        </div>
        <div class="dev-notice">
            Development Mode - Template ID: {$templateId}
        </div>
        <div class="content">
            <div class="buttons">
                {$buttonHtml}
            </div>
            <h4>Template Data:</h4>
            <ul>
                {$dataHtml}
            </ul>
        </div>
        <div class="footer">
            This email was sent via MailHog in development mode.
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function isUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
}
