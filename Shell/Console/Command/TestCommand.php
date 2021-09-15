<?php
namespace TurnTo\SocialCommerce\Shell\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
/**
 * Class TestCommand
 */
class TestCommand extends Command
{

    public function __construct(
        \TurnTo\SocialCommerce\Model\Export\Catalog $catalogExport
    ) {
	$this->catalogExport = $catalogExport;
	parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('turnto:test')->setDescription('Run Catalog Export');
    }
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
	$this->catalogExport->cronUploadFeed();
    }
}
