<?php
declare(strict_types=1);
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Main\Web\Uri;
use Bitrix\Main\SystemException;

class MUrouting extends CBitrixComponent {
    private array $iblockIds = [];
    private array $linkPropNames = [];
    private array $urlStructure;
    private string $route;
    private array $routeSegments;

    public function onPrepareComponentParams($arParams): array {
        $arParams["SHOW_404"] = 'Y';
        $arParams["FILTER_NAME"] = 'arrRoutingFilter';
        $arParams["CACHE_TIME"] ??= 3600;
        $arParams["SET_TITLE"] ??= 'Y';
        return parent::onPrepareComponentParams($arParams);
    }

    public function onIncludeComponentLang() {
    }

    public function executeComponent(): void {
        try {
            $this->getBaseUrl();
            $this->parseUrl();
            $this->prepareStructure();
            $this->checkModules();

            if (!$this->StartResultCache($this->arParams['CACHE_TIME'], $this->arResult['FOLDER'] . $this->route)) return;
            $this->setResultCacheKeys(['FOLDER', 'VARIABLES', 'URL_TEMPLATES']);

            $this->validateRoute();
            $this->includeComponentTemplate($this->arResult['VARIABLES']['TEMPLATE']);

            $this->endResultCache();
        } catch (SystemException $e) {
            $this->abortResultCache();
            $this->handleError($e);
        }
    }

    private function getBaseUrl(): void {
        $this->arResult['FOLDER'] = rtrim($this->arParams['FOLDER'] ?: dirname(Context::getCurrent()->getRequest()->getScriptFile()), '/') . '/';
    }

    private function parseUrl(): void {
        $request = Context::getCurrent()->getRequest();
        $currentUri = str_replace($this->arResult['FOLDER'], '', $request->getDecodedUri());
        $uri = new Uri($currentUri);
        $this->route = $uri->getPath() ?: '/';
        $this->routeSegments = $this->route === '/' ? [] : explode('/', trim($this->route, '/'));
    }

    private function checkModules(): void {
        if (!Loader::includeModule('iblock')) {
            throw new SystemException('Модуль iblock не подключен!');
        }
    }

    private function prepareStructure(): void {
        if ($this->arParams['SEF_MODE'] !== 'Y') {
            throw new SystemException("Для работы компонента {$this->getName()} требуется включенная поддержка ЧПУ!");
        }
        if (!$this->arParams['FACULTIES_IBLOCK_ID']) {
            throw new SystemException("Необходимо указать ID основного инфоблока!");
        }
        $this->urlStructure = array_values($this->arParams['SEF_URL_TEMPLATES']);
        $config = [
            'base' => [
                'iblock' => 'FACULTIES_IBLOCK_ID',
                'property' => null,
            ],
            1 => [
                'iblock' => 'DEPARTMENTS_IBLOCK_ID',
                'property' => 'DEPARTMENTS_LINK_PROPERTY_ID',
            ],
            3 => [
                'iblock' => 'NEWS_IBLOCK_ID',
                'property' => 'NEWS_LINK_PROPERTY_ID',
            ]
        ];
        foreach ($config as $key => $item) {
            $segment = is_int($key) ? trim($this->urlStructure[$key], "/") : $key;
            if ($item['iblock']) {
                $this->iblockIds[$segment] = (int)$this->arParams[$item['iblock']];
            }
            if ($item['property']) {
                $this->linkPropNames[$segment] = $this->arParams[$item['property']];
            }
        }
    }

    private function isSection(string $segment): bool {
        return (bool)$this->iblockIds[$segment];
    }

    private function validateRoute(): void {
        $this->initDefaultVariables();
        if (empty($this->routeSegments)) return;

        $propertyName = '';
        $currentElementID = null;
        $parentElementID = null;
        $optFilter = [];
        $parentIblockID = $this->iblockIds['base'];

        foreach ($this->routeSegments as $key => $segment) {
            if ($this->isSection($segment)) {
                $propertyName = $this->linkPropNames[$segment];
                $parentIblockID = $this->iblockIds[$segment];
                $this->validateSegment($key, $segment);
            } else {
                if ($propertyName && $parentElementID) {
                    $optFilter = [$propertyName . '.VALUE' => $parentElementID];
                }
                $currentElementID = $this->getElementId($parentIblockID, $segment, $optFilter);
                $this->validateElement($currentElementID);
                $parentElementID = $currentElementID;
            }
        }
        $this->updateFilter($propertyName, $parentElementID);
        $this->updateTemplates($key, $segment);
        $this->updateVariables($parentIblockID, $segment);
    }

    private function validateSegment(int $index, string $segment): void {
        if (trim($this->urlStructure[$index], '/') != $segment) {
            $this->return404();
        }
    }

    private function validateElement(int $elementID): void {
        if (!$elementID) {
            $this->return404();
        }
    }

    private function initDefaultVariables(): void {
        $this->arResult['VARIABLES'] = [
            'IBLOCK_ID' => $this->iblockIds['base'],
            'ELEMENT_CODE' => ''
        ];
        $this->arResult['VARIABLES']['TEMPLATE'] = 'list';
        $this->arResult['URL_TEMPLATES']['detail'] = $this->route . $this->urlStructure[0];
    }

    private function updateVariables(int $parrentIblockId, string $segment): void {
        $this->arResult['VARIABLES']['IBLOCK_ID'] = $parrentIblockId;
        if ($this->isSection($segment)) {
            $this->arResult['VARIABLES']['TEMPLATE'] = 'list';
        } else {
            $this->arResult['VARIABLES']['TEMPLATE'] = 'detail';
            $this->arResult['VARIABLES']['ELEMENT_CODE'] = $segment;
        }
    }

    private function updateTemplates(int $index, string $segment): void {
        unset($this->arResult['URL_TEMPLATES']);
        if ($index >= count($this->urlStructure) - 1) return;
        $nextLink = $this->isSection($segment) ? 'detail' : 'list';
        $this->arResult["URL_TEMPLATES"][$nextLink] = $this->route . $this->urlStructure[$index + 1];
    }

    private function updateFilter(string $propertyName, int $elementId): void {
        $GLOBALS[$this->arParams['FILTER_NAME']] = ['PROPERTY_' . $propertyName => $elementId];
    }

    public function getIblockClass(int $id): string {
        $iblock = \Bitrix\Iblock\Iblock::wakeUp($id);
        if (!($entityClass = $iblock->getEntityDataClass())) {
            throw new SystemException("Для инфоблока ID {$id} должен быть заполнен 'Символьный код API' и включен доступ через REST!");
        }
        return $entityClass;
    }

    private function getElementId(int $iblockId, string $code, array $optFilter = []): mixed {
        $class = $this->getIblockClass($iblockId);
        $filter = [
            'CODE' => $code,
            'ACTIVE' => 'Y',
        ];
        if ($optFilter) {
            $filter = array_merge($filter, $optFilter);
        }
        $element = $class::getList([
            'filter' => $filter,
            'select' => ['ID'],
            'cache' => ['ttl' => $this->arParams['CACHE_TIME']],
        ])->fetch();
        return (int)$element['ID'];
    }

    private function handleError(SystemException $e): void {
        if ($e->getMessage() === '404') {
            $this->return404();
        } else {
            echo '<span style="color:red">' . $e->getMessage() . '</span>';
        }
    }

    private function return404(string $message = 'Страница не найдена'): void {
        \Bitrix\Iblock\Component\Tools::process404(
            $message,
            true,
            true,
            true
        );
    }
}