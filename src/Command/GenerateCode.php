<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;
use Twig\Extra\String\StringExtension;
use Twig\Loader\FilesystemLoader;

final class GenerateCode extends Command
{
    private Environment $twig;

    protected static $defaultName = 'gen';

    protected static $defaultDescription = 'Generates code using given template and variables';

    public function __construct(string $templatesDirectory)
    {
        parent::__construct();

        $this->twig = new Environment(
            new FilesystemLoader($templatesDirectory),
            [
                'autoescape' => false,
                'cache' => false,
                'strict_variables' => true,
            ]
        );

        $this->twig->addExtension(new StringExtension());
    }

    protected function configure(): void
    {
        $this->addArgument(
            'templateName',
            InputArgument::REQUIRED,
            'Name of template to be used, without extension. Corresponding .twig file should exist in templates directory.',
        );
        $this->addArgument(
            'destinationFilePath',
            InputArgument::REQUIRED,
            'Absolute path to destination file.',
        );
        $this->addArgument(
            'values',
            InputArgument::IS_ARRAY,
            'Values to be replaced in template, in format key:value. {{name}} and {{namespace}} will be fulfilled automatically based on destination file path.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // parse values to associative array
        $valuesAssociative = [];
        foreach ($input->getArgument('values') as $inputValue) {
            [$key, $value] = explode(':', $inputValue);
            $valuesAssociative[$key] = $value;
        }

        // determine class name and namespace based on destination file path
        $destinationFilePath = $input->getArgument('destinationFilePath');
        $destinationDir = dirname($destinationFilePath);
        $valuesAssociative['name'] = pathinfo($destinationFilePath, PATHINFO_FILENAME);
        $valuesAssociative['namespace'] = str_replace('/', '\\', $destinationDir);

        // create directory if it does not exist
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0777, true);
        }

        $template = $this->twig->load($input->getArgument('templateName') . '.twig');

        file_put_contents(
            $input->getArgument('destinationFilePath'),
            $template->render($valuesAssociative),
        );

        return Command::SUCCESS;
    }
}