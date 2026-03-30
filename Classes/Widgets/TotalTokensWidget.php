<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata\Widgets;

use Mfd\Ai\FileMetadata\Widgets\Provider\TotalTokensDataProvider;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;

class TotalTokensWidget implements WidgetInterface, RequestAwareWidgetInterface
{
    private ServerRequestInterface $request;

    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        private readonly TotalTokensDataProvider $dataProvider,
        private readonly BackendViewFactory $backendViewFactory,
        private readonly array $options = [],
    ) {}

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function renderWidgetContent(): string
    {
        $totals = $this->dataProvider->getTotals();

        $view = $this->backendViewFactory->create($this->request, ['mfd/ai-filemetadata', 'typo3/cms-dashboard']);
        $view->assignMultiple([
            'totals' => $totals,
            'options' => $this->options,
            'configuration' => $this->configuration,
        ]);

        return $view->render('Widget/TotalTokensWidget');
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
