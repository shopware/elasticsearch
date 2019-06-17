<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Search;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class EntitySearcher implements EntitySearcherInterface
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var EntitySearcherInterface
     */
    private $decorated;

    /**
     * @var DefinitionRegistry
     */
    private $registry;

    /**
     * @var ElasticsearchHelper
     */
    private $helper;

    public function __construct(
        Client $client,
        EntitySearcherInterface $searcher,
        DefinitionRegistry $registry,
        ElasticsearchHelper $helper
    ) {
        $this->client = $client;
        $this->decorated = $searcher;
        $this->registry = $registry;
        $this->helper = $helper;
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        if (!$this->helper->allowSearch($definition, $context)) {
            return $this->decorated->search($definition, $criteria, $context);
        }

        $search = $this->createSearch($criteria, $definition, $context);

        $result = $this->client->search([
            'index' => $this->registry->getIndex($definition, $context),
            'type' => $definition->getEntityName(),
            'body' => $search->toArray(),
        ]);

        $data = [];
        foreach ($result['hits']['hits'] as $hit) {
            $id = $hit['_id'];
            $data[$id] = [
                'primary_key' => $id,
                'score' => $hit['_score'],
            ];
        }

        return new IdSearchResult(
            (int) $result['hits']['total'],
            $data,
            $criteria,
            $context
        );
    }

    protected function createSearch(Criteria $criteria, EntityDefinition $definition, Context $context): Search
    {
        $search = new Search();

        $this->helper->addFilters($definition, $criteria, $search, $context);
        $this->helper->addPostFilters($definition, $criteria, $search, $context);
        $this->helper->addQueries($definition, $criteria, $search, $context);
        $this->helper->addSortings($definition, $criteria, $search, $context);

        $search->setSize($criteria->getLimit());
        $search->setFrom($criteria->getOffset());

        return $search;
    }
}
