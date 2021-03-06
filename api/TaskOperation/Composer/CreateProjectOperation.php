<?php

declare(strict_types=1);

/*
 * This file is part of Contao Manager.
 *
 * (c) Contao Association
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerApi\TaskOperation\Composer;

use Contao\ManagerApi\Composer\Environment;
use Contao\ManagerApi\I18n\Translator;
use Contao\ManagerApi\Task\TaskConfig;
use Contao\ManagerApi\Task\TaskStatus;
use Contao\ManagerApi\TaskOperation\AbstractInlineOperation;
use Symfony\Component\Filesystem\Filesystem;

class CreateProjectOperation extends AbstractInlineOperation
{
    /**
     * @var array
     */
    private static $supportedVersions = ['4.4', '4.9'];

    /**
     * @var Environment
     */
    private $environment;

    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $version;

    /**
     * Constructor.
     */
    public function __construct(TaskConfig $taskConfig, Environment $environment, Translator $translator, Filesystem $filesystem)
    {
        parent::__construct($taskConfig);

        $this->environment = $environment;
        $this->translator = $translator;
        $this->filesystem = $filesystem;
        $this->version = $taskConfig->getOption('version');

        if (!\in_array($this->version, static::$supportedVersions, true)) {
            throw new \InvalidArgumentException('Unsupported Contao version');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateStatus(TaskStatus $status): void
    {
        $status->setSummary($this->translator->trans('taskoperation.create-project.summary'));
        $status->setDetail('contao/managed-edition '.$this->version);

        $this->addConsoleStatus($status);
    }

    /**
     * {@inheritdoc}
     */
    protected function addConsoleStatus(TaskStatus $status): void
    {
        $status->addConsole('> Installing contao/managed-edition '.$this->version);

        if ($console = $this->taskConfig->getState($this->getName().'.console')) {
            $status->addConsole((string) $console);
        }

        parent::addConsoleStatus($status);
    }

    /**
     * {@inheritdoc}
     */
    protected function getName(): string
    {
        return 'create-project';
    }

    /**
     * {@inheritdoc}
     */
    protected function doRun(): bool
    {
        $protected = [
            $this->environment->getJsonFile(),
            $this->environment->getLockFile(),
            $this->environment->getVendorDir(),
        ];

        if ($this->filesystem->exists($protected)) {
            throw new \RuntimeException('Cannot install into existing application');
        }

        $this->filesystem->dumpFile(
            $this->environment->getJsonFile(),
            $this->generateComposerJson(
                $this->taskConfig->getOption('version'),
                (bool) $this->taskConfig->getOption('core-only', false)
            )
        );

        return true;
    }

    private function generateComposerJson($version, $coreOnly = false)
    {
        if ($coreOnly) {
            $require = <<<JSON
        "contao/manager-bundle": "$version.*",
        "contao/conflicts": "*@dev"
JSON;
        } else {
            $require = <<<JSON
        "contao/manager-bundle": "$version.*",
        "contao/calendar-bundle": "$version.*",
        "contao/comments-bundle": "$version.*",
        "contao/faq-bundle": "$version.*",
        "contao/listing-bundle": "$version.*",
        "contao/news-bundle": "$version.*",
        "contao/newsletter-bundle": "$version.*",
        "contao/conflicts": "*@dev"
JSON;
        }

        return <<<JSON
{
    "type": "project",
    "require": {
$require
    },
    "extra": {
        "contao-component-dir": "assets"
    },
    "scripts": {
        "post-install-cmd": [
            "Contao\\\\ManagerBundle\\\\Composer\\\\ScriptHandler::initializeApplication"
        ],
        "post-update-cmd": [
            "Contao\\\\ManagerBundle\\\\Composer\\\\ScriptHandler::initializeApplication"
        ]
    }
}
JSON;
    }
}
