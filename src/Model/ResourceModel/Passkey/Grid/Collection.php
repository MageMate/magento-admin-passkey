<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Model\ResourceModel\Passkey\Grid;

use MageMate\AdminPasskey\Api\Data\PasskeyInterface;
use MageMate\AdminPasskey\Model\ResourceModel\Passkey as PasskeyResource;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface as Logger;

/**
 * Admin passkey grid data source.
 *
 * Adds a derived status column, joins the owner username and — unless the
 * current admin holds the manage_all privilege — restricts rows to the
 * current admin's own passkeys.
 */
class Collection extends SearchResult
{
    private const RESOURCE_MANAGE_ALL = 'MageMate_AdminPasskey::manage_all';

    /**
     * @var AuthSession
     */
    private AuthSession $authSession;

    /**
     * @var AuthorizationInterface
     */
    private AuthorizationInterface $authorization;

    /**
     * @param EntityFactory $entityFactory
     * @param Logger $logger
     * @param FetchStrategy $fetchStrategy
     * @param EventManager $eventManager
     * @param AuthSession $authSession
     * @param AuthorizationInterface $authorization
     * @param string $mainTable
     * @param string $resourceModel
     */
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        AuthSession $authSession,
        AuthorizationInterface $authorization,
        $mainTable = 'magemate_admin_passkey',
        $resourceModel = PasskeyResource::class
    ) {
        $this->authSession = $authSession;
        $this->authorization = $authorization;
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $mainTable, $resourceModel);
    }

    /**
     * @inheritdoc
     */
    protected function _initSelect()
    {
        parent::_initSelect();

        $connection = $this->getConnection();
        $statusExpression = $connection->getCheckSql(
            'main_table.' . PasskeyInterface::IS_ACTIVE . ' = 0',
            $connection->quote('inactive'),
            $connection->getCheckSql(
                'main_table.' . PasskeyInterface::EXPIRES_AT
                . ' IS NOT NULL AND main_table.' . PasskeyInterface::EXPIRES_AT . ' < UTC_TIMESTAMP()',
                $connection->quote('expired'),
                $connection->quote('active')
            )
        );

        $this->getSelect()
            ->joinLeft(
                ['admin_user' => $this->getTable('admin_user')],
                'admin_user.user_id = main_table.' . PasskeyInterface::USER_ID,
                ['username' => 'admin_user.username']
            )
            ->columns(['status' => $statusExpression]);

        $this->restrictToOwner();

        return $this;
    }

    /**
     * Restrict the grid to the current admin's own passkeys unless manage_all is granted.
     *
     * @return void
     */
    private function restrictToOwner(): void
    {
        if ($this->authorization->isAllowed(self::RESOURCE_MANAGE_ALL)) {
            return;
        }

        $user = $this->authSession->getUser();
        $userId = $user !== null ? (int)$user->getId() : 0;
        $this->getSelect()->where('main_table.' . PasskeyInterface::USER_ID . ' = ?', $userId);
    }
}
