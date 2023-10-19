<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class Mailer
{
    private const ERROR_TEMPLATE_NAME            = 'error_mail';
    private const ERROR_SUBJECT                  = 'REIFF Schnittstellen-Fehler';
    private const ORDER_MONITORING_TEMPLATE_NAME = 'status_mail';
    private const ORDER_MONITORING_SUBJECT       = 'REIFF Auffällige Aufträge';
    private const MAIL_SENDER_NAME               = 'REIFF Shop-Schnittstelle';

    protected AbstractMailService $mailService;
    protected SystemConfigService $systemConfigService;
    private LoggerInterface $logger;

    public function __construct(
        AbstractMailService $mailService,
        SystemConfigService $systemConfigService,
        LoggerInterface $logger
    ) {
        $this->mailService         = $mailService;
        $this->systemConfigService = $systemConfigService;
        $this->logger              = $logger;
    }

    public function sendStatusMail(array $templateData, Context $context): void
    {
        try {
            $data = new DataBag([
                'recipients'     => $this->getErrorRecipients(),
                'senderName'     => self::MAIL_SENDER_NAME,
                'contentHtml'    => $this->getContentHtml(self::ORDER_MONITORING_TEMPLATE_NAME),
                'contentPlain'   => $this->getContentPlain(self::ORDER_MONITORING_TEMPLATE_NAME),
                'salesChannelId' => null,
                'subject'        => self::ORDER_MONITORING_SUBJECT,
            ]);

            $this->mailService->send($data->all(), $context, $templateData);
        } catch (\Throwable $exception) {
            $this->logger->error('Could not send status mail.', ['exception', $exception, 'templateData' => $templateData]);
        }
    }

    /**
     * @param \Throwable[] $errors
     */
    public function sendErrorMail(array $errors, string $filename, Context $context): void
    {
        try {
            $data = new DataBag([
                'recipients'     => $this->getErrorRecipients(),
                'senderName'     => self::MAIL_SENDER_NAME,
                'contentHtml'    => $this->getContentHtml(self::ERROR_TEMPLATE_NAME),
                'contentPlain'   => $this->getContentPlain(self::ERROR_TEMPLATE_NAME),
                'salesChannelId' => null,
                'subject'        => self::ERROR_SUBJECT,
            ]);

            $this->mailService->send($data->all(), $context, [
                'filename' => $filename,
                'errors'   => $errors,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Could not send error mail.', [
                'exception' => $exception,
                'filename'  => $filename,
                'errors'    => $errors,
            ]);
        }
    }

    protected function getErrorRecipients(): array
    {
        $recipients = $this->systemConfigService->getString(Configuration::CONFIG_KEY_ERROR_RECIPIENT);

        if (empty($recipients)) {
            $recipients = $this->getDefaultRecipient();
        }

        $list = explode(',', $recipients);
        $list = array_map('trim', $list);
        $list = array_filter($list, 'strlen');

        if (empty($list)) {
            throw new \RuntimeException('no error mail recipients found');
        }

        return (array) array_combine($list, $list);
    }

    protected function getDefaultRecipient(): string
    {
        $senderEmail = $this->systemConfigService->getString('core.basicInformation.email');

        if (empty($senderEmail)) {
            $senderEmail = $this->systemConfigService->getString('core.mailerSettings.senderAddress');
        }

        return $senderEmail;
    }

    private function getContentHtml(string $type): string
    {
        $filename = __DIR__ . '/templates/' . $type . '.html.twig';

        if (!file_exists($filename)) {
            throw new \RuntimeException(sprintf('Can\'t find mail template %s', $filename));
        }

        return (string) file_get_contents($filename);
    }

    private function getContentPlain(string $type): string
    {
        $filename = __DIR__ . '/templates/' . $type . '.txt.twig';

        if (!file_exists($filename)) {
            throw new \RuntimeException(sprintf('Can\'t find mail template %s', $filename));
        }

        return (string) file_get_contents($filename);
    }
}
