<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\CustomOrderNumber\MessageHandler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Psr\Log\LoggerInterface;
use ReiffIntegrations\Sap\CustomOrderNumber\Api\Client\OrderNumberApiClient;
use ReiffIntegrations\Sap\CustomOrderNumber\Message\OrderNumberUpdateMessage;
use ReiffIntegrations\Sap\CustomOrderNumber\Struct\OrderNumberUpdateStruct;
use ReiffIntegrations\Util\Context\DryRunState;
use Shopware\B2B\Common\Repository\NotFoundException;
use Shopware\B2B\Common\UuidIdValue;
use Shopware\B2B\Debtor\Framework\DebtorRepositoryInterface;
use Shopware\B2B\OrderNumber\Framework\OrderNumberCrudService;
use Shopware\B2B\OrderNumber\Framework\OrderNumberFileEntity;
use Shopware\B2B\OrderNumber\Framework\OrderNumberFileValidationException;
use Shopware\B2B\StoreFrontAuthentication\Framework\LoginContextService;
use Shopware\B2B\StoreFrontAuthentication\Framework\OwnershipContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\ConstraintViolation;

#[AsMessageHandler(fromTransport: 'import')]
class OrderNumberUpdateMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Connection $connection,
        private readonly OrderNumberApiClient $orderNumberApiClient,
        private readonly OrderNumberCrudService $orderNumberCrudService,
        private readonly LoginContextService $loginContextService,
        private readonly DebtorRepositoryInterface $debtorRepository
    ) {
    }

    public function __invoke(OrderNumberUpdateMessage $message): void
    {
        $context      = $message->getContext();
        $updateStruct = $message->getUpdateStruct();
        $customerId   = $updateStruct->getCustomerId();

        try {
            $debtorIdentity = $this->debtorRepository->fetchIdentityById(UuidIdValue::create($customerId), $this->loginContextService);
        } catch (NotFoundException $notFoundException) {
            $this->logger->error('Could not find debtor for OrderNumberUpdateMessage' . $notFoundException->getMessage(), [
                'customerId' => $customerId,
            ]);

            return;
        }

        $crudData = $this->getCrudData($updateStruct);
        $this->executeCrudOperation($crudData, $debtorIdentity->getOwnershipContext(), $context);
    }

    /**
     * @param OrderNumberUpdateStruct $struct
     */
    public function getMessage(Struct $struct, Context $context): OrderNumberUpdateMessage
    {
        return new OrderNumberUpdateMessage($struct, $context);
    }

    private function getCrudData(OrderNumberUpdateStruct $updateStruct): array
    {
        try {
            $response = $this->orderNumberApiClient->readOrderNumbers($updateStruct);
        } catch (\Throwable $t) {
            $this->logger->error(self::class . '::getCrudData => something went horribly wrong during read of order numbers', [
                'message' => $t->getMessage(),
                'code'    => $t->getCode(),
            ]);

            return [];
        }

        $iterator = 0;
        $crudData = [];

        if ($response->getDocuments()->count() === 0) {
            $this->logger->error(self::class . '::getCrudData => no documents found', [
                'success'       => $response->isSuccess(),
                'returnMessage' => $response->getReturnMessage(),
                'rawResponse'   => $response->getRawResponse(),
            ]);

            return [];
        }

        foreach ($response->getDocuments()->getElements() as $document) {
            $orderNumberEntity = new OrderNumberFileEntity();

            $orderNumberEntity->row               = $iterator;
            $orderNumberEntity->orderNumber       = $document->getSapNumber();
            $orderNumberEntity->customOrderNumber = $document->getCustomerNumber();

            $crudData[] = $orderNumberEntity;
            ++$iterator;
        }

        return $crudData;
    }

    private function executeCrudOperation(array $crudData, OwnershipContext $ownershipContext, Context $context, int $retries = 0): void
    {
        if (empty($crudData)) {
            $this->logger->warning(self::class . '::executeCrudOperation => no crud data found');

            return;
        }

        try {
            $this->connection->beginTransaction();
            $this->orderNumberCrudService->replace($crudData, $ownershipContext);

            if ($context->hasState(DryRunState::NAME)) {
                $this->connection->rollBack();
            } else {
                $this->connection->commit();
            }
        } catch (\Throwable $throwable) {
            try {
                $this->connection->rollBack();
            } catch (ConnectionException $e) {
                $this->logger->error(self::class . '::executeCrudOperation: Rollback for data could not be executed', [
                    'message' => $e->getMessage(),
                    'code'    => $throwable->getCode(),
                    'line'    => $throwable->getLine(),
                    'file'    => $throwable->getFile(),
                    'data'    => \json_encode($crudData),
                ]);
            }

            if ($retries < 3) {
                $crudData = $this->removeInvalidProductNumbers($crudData, $throwable);
                $this->executeCrudOperation($crudData, $ownershipContext, $context, $retries + 1);
            }
        }
    }

    /**
     * @param OrderNumberFileEntity[] $crudData
     */
    private function removeInvalidProductNumbers(array $crudData, \Throwable $throwable): array
    {
        if ($throwable instanceof OrderNumberFileValidationException) {
            $violatedOrderNumbers = [];

            foreach ($throwable->getViolations() as $violation) {
                if ($violation instanceof ConstraintViolation && $violation->getMessageTemplate() === 'This value %value% is not available Line %line%.') {
                    $violatedOrderNumbers[] = $violation->getRoot();
                }

                if ($violation instanceof ConstraintViolation && $violation->getMessageTemplate() === 'This value %value% must not contain special chars Line %line%') {
                    $parameters = $violation->getParameters();

                    if (array_key_exists('%line%', $parameters) && !empty($parameters['%line%'])) {
                        $violatedOrderNumbers[] = $crudData[(int) $parameters['%line%']]->orderNumber;
                    }
                }
            }

            if (!empty($violatedOrderNumbers)) {
                $this->logger->info(sprintf('The following product numbers can not be inserted: %s', implode(',', $violatedOrderNumbers)));
            }

            $iterator = 0;
            foreach ($crudData as $key => $crudRow) {
                if (in_array($crudRow->orderNumber, $violatedOrderNumbers)) {
                    unset($crudData[$key]);

                    continue;
                }

                $crudRow->row = $iterator;
                ++$iterator;
            }
        }

        return $crudData;
    }
}
