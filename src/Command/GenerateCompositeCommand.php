<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Command;

use ArniePalau\CcComposite\Service\RegenerationService;
use Forumify\PerscomPlugin\Perscom\Repository\PerscomUserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'cc-composite:generate', description: 'Generate one or all PERSCOM composite Uniform images.')]
final class GenerateCompositeCommand extends Command
{
    public function __construct(
        private readonly PerscomUserRepository $userRepository,
        private readonly RegenerationService $regenerationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::OPTIONAL, 'Forumify PERSCOM user ID')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Generate every soldier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($input->getOption('all')) {
            $count = $this->regenerationService->regenerateAll();
            $io->success(sprintf('Generated %d composite image(s).', $count));
            return Command::SUCCESS;
        }

        $id = $input->getArgument('user');
        if ($id === null || ($user = $this->userRepository->find($id)) === null) {
            $io->error('Provide a valid PERSCOM user ID, or use --all.');
            return Command::INVALID;
        }

        if (!$this->regenerationService->regenerate($user)) {
            $io->error('Composite generation failed. Check the application log.');
            return Command::FAILURE;
        }
        $io->success('Composite image generated.');

        return Command::SUCCESS;
    }
}
