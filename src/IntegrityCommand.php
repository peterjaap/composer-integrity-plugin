<?php

namespace Sansec\Integrity;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class IntegrityCommand extends BaseCommand
{
    private const VERDICT_TYPES = [
        'unknown' => '<fg=white>?</>',
        'match' => '<fg=green>✓</>',
        'mismatch' => '<fg=red>⨉</>'
    ];

    private const OPTION_NAME_SKIP_MATCH = 'skip-match';

    public function __construct(private readonly PackageSubmitter $submitter, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('integrity')
            ->setDescription('Checks composer integrity.')
            ->addOption(self::OPTION_NAME_SKIP_MATCH, null, InputOption::VALUE_OPTIONAL, 'Skip matching checksums.', false);
    }

    private function getPercentage(PackageVerdict $packageVerdict): string
    {
        if ($packageVerdict->verdict == 'unknown') {
            return '-';
        }

        return $packageVerdict->percentage . '%';
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $verdicts = $this->submitter->getPackageVerdicts($output);

        if ($input->getOption(self::OPTION_NAME_SKIP_MATCH) !== false) {
            $verdicts = array_filter($verdicts, fn (PackageVerdict $verdict) => $verdict->verdict != 'match');
        }

        $table = new Table($output);
        $table
            ->setHeaders(['Status', 'Package', 'Version', 'Package ID', 'Checksum', 'Percentage'])
            ->setRows(array_map(fn (PackageVerdict $packageVerdict) => [
                self::VERDICT_TYPES[$packageVerdict->verdict],
                $packageVerdict->name,
                $packageVerdict->version,
                $packageVerdict->id,
                $packageVerdict->checksum,
                $this->getPercentage($packageVerdict)
            ], $verdicts));

        foreach ([0, 5] as $centeredColumnId) {
            $table->setColumnStyle($centeredColumnId, (new TableStyle())->setPadType(STR_PAD_BOTH));
        }
        $table->render();

        $mismatching = array_filter($verdicts, fn (PackageVerdict $verdict) => $verdict->verdict == 'mismatch');

        return count($mismatching) > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
