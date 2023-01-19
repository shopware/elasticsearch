<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\Command\ConsoleProgressTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('system-settings')]
final class ElasticsearchAdminIndexingCommand extends Command implements EventSubscriberInterface
{
    use ConsoleProgressTrait;

    protected static $defaultName = 'es:admin:index';

    protected static $defaultDescription = 'Index the elasticsearch for the admin search';

    private AdminSearchRegistry $registry;

    /**
     * @internal
     */
    public function __construct(AdminSearchRegistry $registry)
    {
        parent::__construct();
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption('no-queue', null, null, 'Do not use the queue for indexing');
        $this->addOption('skip', null, InputArgument::OPTIONAL, 'Comma separated list of entity names to be skipped');
        $this->addOption('only', null, InputArgument::OPTIONAL, 'Comma separated list of entity names to be generated');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ShopwareStyle($input, $output);

        $skip = \is_string($input->getOption('skip')) ? explode(',', $input->getOption('skip')) : [];
        $only = \is_string($input->getOption('only')) ? explode(',', $input->getOption('only')) : [];

        $this->registry->iterate(
            new AdminIndexingBehavior(
                (bool) $input->getOption('no-queue'),
                $skip,
                $only
            )
        );

        return self::SUCCESS;
    }
}
