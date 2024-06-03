<?php

    declare(strict_types=1);

    namespace ReiffIntegrations\Migration;

    use DateTime;
    use Doctrine\DBAL\Connection;
    use Shopware\Core\Framework\Migration\MigrationStep;
    use Shopware\Core\Defaults;
    use Shopware\Core\Framework\Uuid\Uuid;


    class Migration1717401131PriceRequestMailTemplate extends MigrationStep
    {
        const MAIL_TEMPLATE_TECHNICAL_NAME = 'request_product_price_mail_template';

        public function getCreationTimestamp(): int
        {
            return 1717401131;
        }

        public function update(Connection $connection): void
        {
            $mailTemplateId = $this->createMailTemplateType($connection);
            $this->createMailTemplate($connection, $mailTemplateId);
        }

        public function updateDestructive(Connection $connection): void
        {
        }

        private function createMailTemplateType(Connection $connection): string
        {
            $mailTemplateTypeId = Uuid::randomHex();

            $enGbLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
            $deDeLangId = $this->getLanguageIdByLocale($connection, 'de-DE');
            $nlNlLangId = $this->getLanguageIdByLocale($connection, 'nl-NL');
            $frFrLangId = $this->getLanguageIdByLocale($connection, 'fr-FR');

            $englishName = 'Request Product Price Mail Template';
            $germanName = 'Preisanfrage Mail Template';
            $dutchName = 'Prijsaanvraag Mail Template';
            $frenchName = 'Demande de prix Mail Template';

            $connection->executeStatement("
                INSERT IGNORE INTO `mail_template_type`
                    (id, technical_name, available_entities, created_at)
                VALUES
                    (:id, :technicalName, :availableEntities, :createdAt)
            ",[
                'id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'technicalName' => self::MAIL_TEMPLATE_TECHNICAL_NAME,
                'availableEntities' => json_encode([
                    'product' => 'product',
                    'customer' => 'customer',
                ]),
                'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            if (!empty($enGbLangId)) {
                $connection->executeStatement("
                    INSERT IGNORE INTO `mail_template_type_translation`
                        (mail_template_type_id, language_id, name, created_at)
                    VALUES
                        (:mailTemplateTypeId, :languageId, :name, :createdAt)
                ",[
                    'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
                    'languageId' => $enGbLangId,
                    'name' => $englishName,
                    'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }

            if (!empty($deDeLangId)) {
                $connection->executeStatement("
                    INSERT IGNORE INTO `mail_template_type_translation`
                        (mail_template_type_id, language_id, name, created_at)
                    VALUES
                        (:mailTemplateTypeId, :languageId, :name, :createdAt)
                ",[
                    'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
                    'languageId' => $deDeLangId,
                    'name' => $germanName,
                    'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }

            if (!empty($nlNlLangId)) {
                $connection->executeStatement("
                    INSERT IGNORE INTO `mail_template_type_translation`
                        (mail_template_type_id, language_id, name, created_at)
                    VALUES
                        (:mailTemplateTypeId, :languageId, :name, :createdAt)
                ",[
                    'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
                    'languageId' => $nlNlLangId,
                    'name' => $dutchName,
                    'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }

            if (!empty($frFrLangId)) {
                $connection->executeStatement("
                    INSERT IGNORE INTO `mail_template_type_translation`
                        (mail_template_type_id, language_id, name, created_at)
                    VALUES
                        (:mailTemplateTypeId, :languageId, :name, :createdAt)
                ",[
                    'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
                    'languageId' => $frFrLangId,
                    'name' => $frenchName,
                    'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }

            return $mailTemplateTypeId;
        }

        private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
        {
            $sql = <<<SQL
                SELECT `language`.`id`
                FROM `language`
                INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
                WHERE `locale`.`code` = :code
            SQL;

            $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();

            if (empty($languageId)) {
                return null;
            }

            return $languageId;
        }

        private function createMailTemplate(Connection $connection, string $mailTemplateTypeId): void
        {
            $mailTemplateId = Uuid::randomHex();

            $enGbLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
            $deDeLangId = $this->getLanguageIdByLocale($connection, 'de-DE');
            $nlNlLangId = $this->getLanguageIdByLocale($connection, 'nl-NL');
            $frFrLangId = $this->getLanguageIdByLocale($connection, 'fr-FR');

            $connection->executeStatement("
                INSERT IGNORE INTO `mail_template`
                    (id, mail_template_type_id, system_default, created_at)
                VALUES
                    (:id, :mailTemplateTypeId, :systemDefault, :createdAt)
                ",[
                    'id' => Uuid::fromHexToBytes($mailTemplateId),
                    'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
                    'systemDefault' => 0,
                    'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);


            if (!empty($enGbLangId)) {
                $connection->executeStatement("
                    INSERT IGNORE INTO `mail_template_translation`
                        (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
                    VALUES
                        (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
                    ",[
                        'mailTemplateId' => Uuid::fromHexToBytes($mailTemplateId),
                        'languageId' => $enGbLangId,
                        'senderName' => '{{ salesChannel.name }}',
                        'subject' => 'Price Request',
                        'description' => 'Price Request Mail Template',
                        'contentHtml' => $this->getContentHtmlEn(),
                        'contentPlain' => $this->getContentPlainEn(),
                        'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }

            if (!empty($deDeLangId)) {
                $connection->executeStatement("
                INSERT IGNORE INTO `mail_template_translation`
                    (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
                VALUES
                    (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
                ",[
                        'mailTemplateId' => Uuid::fromHexToBytes($mailTemplateId),
                        'languageId' => $deDeLangId,
                        'senderName' => '{{ salesChannel.name }}',
                        'subject' => 'Preisanfrage',
                        'description' => 'Preisanfrage Mail Template',
                        'contentHtml' => $this->getContentHtmlDe(),
                        'contentPlain' => $this->getContentPlainDe(),
                        'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }

            if (!empty($nlNlLangId)) {
                $connection->executeStatement("
                INSERT IGNORE INTO `mail_template_translation`
                    (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
                VALUES
                    (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
                ",[
                        'mailTemplateId' => Uuid::fromHexToBytes($mailTemplateId),
                        'languageId' => $nlNlLangId,
                        'senderName' => '{{ salesChannel.name }}',
                        'subject' => 'Prijsaanvraag',
                        'description' => 'Prijsaanvraag Mail Template',
                        'contentHtml' => $this->getContentHtmlNl(),
                        'contentPlain' => $this->getContentPlainNl(),
                        'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }

            if (!empty($frFrLangId)) {
                $connection->executeStatement("
                INSERT IGNORE INTO `mail_template_translation`
                    (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
                VALUES
                    (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
                ",[
                        'mailTemplateId' => Uuid::fromHexToBytes($mailTemplateId),
                        'languageId' => $frFrLangId,
                        'senderName' => '{{ salesChannel.name }}',
                        'subject' => 'Demande de prix',
                        'description' => 'Demande de prix Mail Template',
                        'contentHtml' => $this->getContentHtmlFr(),
                        'contentPlain' => $this->getContentPlainFr(),
                        'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }
        }

        private function getContentHtmlEn(): string {
            return <<<MAIL
                <div style="font-family:arial; font-size:12px;">
                    <p>Please send me an offer for the product {{ product.translated.name }}.</p>
                    <p>Product number: {{ product.productNumber }}</p>
                    <p>Customer number: {{ customer.customerNumber }}</p>
                </div>
            MAIL;
        }

        private function getContentPlainEn(): string {
            return <<<MAIL
                Please send me an offer for the product {{ product.translated.name }}.
                Product number: {{ product.productNumber }}
                Customer number: {{ customer.customerNumber }}
            MAIL;
        }

        private function getContentHtmlDe(): string {
            return <<<MAIL
                <div style="font-family:arial; font-size:12px;">
                    <p>Bitte senden Sie mir ein Angebot für das Produkt {{ product.translated.name }}.</p>
                    <p>Produktnummer: {{ product.productNumber }}</p>
                    <p>Kundennummer: {{ customer.customerNumber }}</p>
                </div>
            MAIL;
        }

        private function getContentPlainDe(): string {
            return <<<MAIL
                Bitte senden Sie mir ein Angebot für das Produkt {{ product.translated.name }}.
                Produktnummer: {{ product.productNumber }}
                Kundennummer: {{ customer.customerNumber }}
            MAIL;
        }

        private function getContentHtmlNl(): string {
            return <<<MAIL
                <div style="font-family:arial; font-size:12px;">
                    <p>Stuur mij een offerte voor het product {{ product.translated.name }}.</p>
                    <p>Productnummer: {{ product.productNumber }}</p>
                    <p>Klantnummer: {{ customer.customerNumber }}</p>
                </div>
            MAIL;
        }

        private function getContentPlainNl(): string {
            return <<<MAIL
                Stuur mij een offerte voor het product {{ product.translated.name }}.
                Productnummer: {{ product.productNumber }}
                Klantnummer: {{ customer.customerNumber }}
            MAIL;
        }

        private function getContentHtmlFr(): string {
            return <<<MAIL
                <div style="font-family:arial; font-size:12px;">
                    <p>Veuillez m'envoyer une offre pour le produit {{ product.translated.name }}.</p>
                    <p>Numéro de produit: {{ product.productNumber }}</p>
                    <p>Numéro de client: {{ customer.customerNumber }}</p>
                </div>
            MAIL;
        }

        private function getContentPlainFr(): string {
            return <<<MAIL
                Veuillez m'envoyer une offre pour le produit {{ product.translated.name }}.
                Numéro de produit: {{ product.productNumber }}
                Numéro de client: {{ customer.customerNumber }}
            MAIL;
        }
    }
