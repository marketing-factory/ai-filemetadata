<?php

declare(strict_types=1);

namespace Mfd\Ai\FileMetadata;

use Mfd\Ai\FileMetadata\Widgets\Provider\TokenUsageChartDataProvider;
use Mfd\Ai\FileMetadata\Widgets\Provider\TokenUsagePerUserDataProvider;
use Mfd\Ai\FileMetadata\Widgets\Provider\TotalTokensDataProvider;
use Mfd\Ai\FileMetadata\Widgets\TokenUsagePerUserWidget;
use Mfd\Ai\FileMetadata\Widgets\TotalTokensWidget;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Dashboard\WidgetRegistry;
use TYPO3\CMS\Dashboard\Widgets\BarChartWidget;

return function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder) {
    $services = $configurator->services();

    /**
     * Check if WidgetRegistry is defined, which means that EXT:dashboard is available.
     * Registration directly in Services.yaml will break without EXT:dashboard installed!
     */
    if ($containerBuilder->hasDefinition(WidgetRegistry::class)) {
        $services->set('dashboard.widget.aiTokenUsageChart')
            ->class(BarChartWidget::class)
            ->arg('$dataProvider', new Reference(TokenUsageChartDataProvider::class))
            ->arg('$backendViewFactory', new Reference(BackendViewFactory::class))
            ->tag(
                'dashboard.widget',
                [
                    'identifier' => 'aiTokenUsageChart',
                    'groupNames' => 'ai',
                    'title' => 'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_be.xlf:widget.tokenUsageChart.title',
                    'description' => 'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_be.xlf:widget.tokenUsageChart.description',
                    'iconIdentifier' => 'content-widget-chart-bar',
                    'height' => 'medium',
                    'width' => 'medium',
                ]
            );

        $services->set('dashboard.widget.aiTotalTokens')
            ->class(TotalTokensWidget::class)
            ->arg('$dataProvider', new Reference(TotalTokensDataProvider::class))
            ->arg('$backendViewFactory', new Reference(BackendViewFactory::class))
            ->tag(
                'dashboard.widget',
                [
                    'identifier' => 'aiTotalTokens',
                    'groupNames' => 'ai',
                    'title' => 'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_be.xlf:widget.totalTokens.title',
                    'description' => 'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_be.xlf:widget.totalTokens.description',
                    'iconIdentifier' => 'content-widget-number',
                    'height' => 'small',
                    'width' => 'small',
                ]
            );

        $services->set('dashboard.widget.aiTokenUsagePerUser')
            ->class(TokenUsagePerUserWidget::class)
            ->arg('$dataProvider', new Reference(TokenUsagePerUserDataProvider::class))
            ->arg('$backendViewFactory', new Reference(BackendViewFactory::class))
            ->tag(
                'dashboard.widget',
                [
                    'identifier' => 'aiTokenUsagePerUser',
                    'groupNames' => 'ai',
                    'title' => 'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_be.xlf:widget.tokenUsagePerUser.title',
                    'description' => 'LLL:EXT:ai_filemetadata/Resources/Private/Language/locallang_be.xlf:widget.tokenUsagePerUser.description',
                    'iconIdentifier' => 'content-widget-list',
                    'height' => 'medium',
                    'width' => 'medium',
                ]
            );
    }
};
