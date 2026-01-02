<?php

namespace App\Service\Mail;

use Psr\Log\LoggerInterface;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Ses\V20201002\Models\SendEmailRequest;
use TencentCloud\Ses\V20201002\Models\Template;
use TencentCloud\Ses\V20201002\SesClient;

class TencentSesMailService implements MailServiceInterface
{
    private SesClient $client;

    public function __construct(
        string $secretId,
        string $secretKey,
        string $region,
        private string $fromEmail,
        private string $fromName,
        private LoggerInterface $logger,
    ) {
        $credential = new Credential($secretId, $secretKey);

        $httpProfile = new HttpProfile();
        $httpProfile->setEndpoint('ses.tencentcloudapi.com');

        $clientProfile = new ClientProfile();
        $clientProfile->setHttpProfile($httpProfile);

        $this->client = new SesClient($credential, $region, $clientProfile);
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

        $request = new SendEmailRequest();

        $request->setFromEmailAddress($this->formatEmailAddress($senderEmail, $senderName));
        $request->setDestination([$to]);
        $request->setSubject($subject);

        $simple = [
            'Html' => base64_encode($htmlContent),
        ];

        if ($textContent !== null) {
            $simple['Text'] = base64_encode($textContent);
        }

        $request->setSimple($simple);

        try {
            $response = $this->client->SendEmail($request);

            $this->logger->info('Email sent successfully via Tencent SES', [
                'to' => $to,
                'subject' => $subject,
                'messageId' => $response->getMessageId(),
            ]);
        } catch (TencentCloudSDKException $e) {
            $this->logger->error('Failed to send email via Tencent SES', [
                'to' => $to,
                'subject' => $subject,
                'errorCode' => $e->getErrorCode(),
                'errorMessage' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                sprintf('Failed to send email: %s', $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
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
        $senderEmail = $fromEmail ?? $this->fromEmail;
        $senderName = $fromName ?? $this->fromName;

        $request = new SendEmailRequest();
        $request->setSubject($subject);
        $request->setFromEmailAddress($this->formatEmailAddress($senderEmail, $senderName));
        $request->setDestination([$to]);

        $tpl = new Template();
        $tpl->setTemplateID($templateId);
        $tpl->setTemplateData(json_encode($templateData, JSON_UNESCAPED_UNICODE));
        $request->setTemplate($tpl);
        try {
            $response = $this->client->SendEmail($request);

            $this->logger->info('Template email sent successfully via Tencent SES', [
                'to' => $to,
                'templateId' => $templateId,
                'messageId' => $response->getMessageId(),
            ]);
        } catch (TencentCloudSDKException $e) {
            $this->logger->error('Failed to send template email via Tencent SES', [
                'to' => $to,
                'templateId' => $templateId,
                'errorCode' => $e->getErrorCode(),
                'errorMessage' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                sprintf('Failed to send template email: %s', $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }
    }

    private function formatEmailAddress(string $email, string $name): string
    {
        if (empty($name)) {
            return $email;
        }

        return sprintf('%s <%s>', $name, $email);
    }
}