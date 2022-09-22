<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
final class OrderAdminSearchIndexer extends AbstractAdminIndexer
{
    private Connection $connection;

    private IteratorFactory $factory;

    private EntityRepositoryInterface $repository;

    public function __construct(Connection $connection, IteratorFactory $factory, EntityRepositoryInterface $repository)
    {
        $this->connection = $connection;
        $this->factory = $factory;
        $this->repository = $repository;
    }

    public function getDecorated(): AbstractAdminIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function getEntity(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'order-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, 150);
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array{total:int, data:EntityCollection<Entity>}
     */
    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }

    /**
     * @param array<string>|array<int, array<string>> $ids
     *
     * @throws \Doctrine\DBAL\Exception
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function fetch(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(`order`.id)) as id',
            'GROUP_CONCAT(tag.name) as tags',
            'GROUP_CONCAT(country_translation.name) as country',
            'GROUP_CONCAT(order_address.city) as city',
            'GROUP_CONCAT(order_address.zipcode) as zipcode',
            'GROUP_CONCAT(order_address.street) as street',
            '`order_customer`.first_name',
            '`order_customer`.last_name',
            '`order_customer`.email',
            '`order_customer`.company',
            '`order_customer`.customer_number',
            '`order`.order_number',
        ]);

        $query->from('`order`');
        $query->leftJoin('`order`', 'order_customer', 'order_customer', '`order`.id = order_customer.order_id');
        $query->leftJoin('`order`', 'order_address', 'order_address', '`order`.id = order_address.order_id');
        $query->leftJoin('order_address', 'country', 'country', 'order_address.country_id = country.id');
        $query->leftJoin('country', 'country_translation', 'country_translation', 'country.id = country_translation.country_id');
        $query->leftJoin('`order`', 'order_tag', 'order_tag', '`order`.id = order_tag.order_id');
        $query->leftJoin('order_tag', 'tag', 'tag', 'order_tag.tag_id = tag.id');
        $query->groupBy('`order`.id');

        $query->where('`order`.id IN (:ids)');
        $query->andWhere('order.version_id = :versionId');
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY);
        $query->setParameter('versionId', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));

        $data = $query->execute()->fetchAll();

        $mapped = [];
        foreach ($data as $row) {
            $id = $row['id'];
            $text = \implode(' ', array_filter(array_unique(array_values($row))));
            $mapped[$id] = ['id' => $id, 'text' => \strtolower($text)];
        }

        return $mapped;
    }
}
