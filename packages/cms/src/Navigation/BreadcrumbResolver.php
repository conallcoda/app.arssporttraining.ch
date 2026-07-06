<?php

namespace Coda\Cms\Navigation;

use Closure;
use Coda\Cms\Area;
use Coda\Cms\Contracts\BreadcrumbLabel;
use Coda\Cms\DetailsPageDefinition;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;
use Coda\Cms\Registry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class BreadcrumbResolver
{
    public function __construct(protected Registry $registry) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @return Crumb[]
     */
    public function resolve(?string $routeName = null, array $parameters = []): array
    {
        $routeName ??= request()->route()?->getName();

        if ($routeName === null) {
            return [];
        }

        $page = $this->registry->page($routeName);

        if ($page === null || $page->breadcrumbs === false) {
            return [];
        }

        if ($parameters === []) {
            $parameters = request()->route()?->parameters() ?? [];
        }

        $crumbs = [];

        $areaChain = $this->areaChain($routeName);
        $topArea = $areaChain[0] ?? null;
        $scopeCrumb = $this->scopeCrumb($areaChain, $routeName);

        if (($topArea === null || ! $topArea->isScoped()) && count($areaChain) > 1) {
            foreach ($areaChain as $area) {
                $navRoute = $area->navigationRoute();
                $href = $navRoute !== null ? $this->safeUrl($navRoute) : null;
                $crumbs[] = new Crumb($area->label, $href);
            }
        }

        if ($scopeCrumb !== null) {
            $crumbs[] = $scopeCrumb;
        }

        $module = $this->moduleForRoute($routeName);
        $moduleIsCurrent = $module !== null && $module->navigationRoute() === $routeName;

        $modRoute = $module?->navigationRoute();

        if ($module !== null && $modRoute !== null) {
            $href = ($modRoute !== null && ! $moduleIsCurrent)
                ? $this->safeUrl($modRoute)
                : null;
            $crumbs[] = new Crumb($module->label(), $href, current: $moduleIsCurrent);
        }

        foreach ($this->parentChain($page, $module, $parameters) as $parentCrumb) {
            $crumbs[] = $parentCrumb;
        }

        if (! $moduleIsCurrent) {
            $crumbs[] = new Crumb(
                label: $this->labelForPage($page, $parameters),
                href: null,
                current: true,
            );
        }

        return $this->dedupe($crumbs);
    }

    /**
     * @return Area[]
     */
    protected function areaChain(string $routeName): array
    {
        $top = $this->registry->areas()
            ->first(fn (Area $area) => $area->matchesRoute($routeName));

        if ($top === null) {
            return [];
        }

        $chain = [$top];

        $cursor = $top;
        while (true) {
            $matchingChild = null;
            foreach ($cursor->children as $child) {
                if ($child->matchesRoute($routeName)) {
                    $matchingChild = $child;
                    break;
                }
            }

            if ($matchingChild === null) {
                break;
            }

            $chain[] = $matchingChild;
            $cursor = $matchingChild;
        }

        return $chain;
    }

    /**
     * @param  Area[]  $areaChain
     */
    protected function scopeCrumb(array $areaChain, string $routeName): ?Crumb
    {
        foreach ($areaChain as $area) {
            if (! $area->isScoped()) {
                continue;
            }

            $context = $this->registry->currentContext($routeName);
            if ($context === null) {
                return null;
            }

            return new Crumb(
                label: $this->labelForModel($context),
                href: null,
            );
        }

        return null;
    }

    protected function moduleForRoute(string $routeName): ?Module
    {
        foreach ($this->registry->modules() as $module) {
            foreach ($module->pages() as $page) {
                if ($page->name === $routeName) {
                    return $module;
                }
            }
            foreach ($module->detailPages() as $page) {
                if ($page->name === $routeName) {
                    return $module;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return Crumb[]
     */
    protected function parentChain(PageDefinition $page, ?Module $module, array $parameters): array
    {
        if ($page->parent !== null) {
            $parentPage = $this->registry->page($page->parent);
            if ($parentPage === null) {
                return [];
            }

            $upstream = $this->parentChain($parentPage, $module, $parameters);

            return [
                ...$upstream,
                new Crumb(
                    label: $this->labelForPage($parentPage, $parameters),
                    href: $this->safeUrl($parentPage->name, $parameters),
                ),
            ];
        }

        if ($page instanceof DetailsPageDefinition && $page->listComponent !== null && $module !== null) {
            $listPage = $this->findListPage($module, $page->listComponent);
            if ($listPage !== null && $listPage->name !== $page->name) {
                return [
                    new Crumb(
                        label: $this->labelForPage($listPage, $parameters),
                        href: $this->safeUrl($listPage->name, $parameters),
                    ),
                ];
            }
        }

        return [];
    }

    protected function findListPage(Module $module, string $listComponent): ?PageDefinition
    {
        foreach ($module->pages() as $page) {
            if ($page->component === $listComponent) {
                return $page;
            }

            foreach ($page->content as $componentName) {
                $component = $this->registry->component($componentName);
                if ($component !== null && $component->component === $listComponent) {
                    return $page;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function labelForPage(PageDefinition $page, array $parameters): string
    {
        if ($page->breadcrumbLabel instanceof Closure) {
            return (string) ($page->breadcrumbLabel)($parameters);
        }

        if (is_string($page->breadcrumbLabel) && $page->breadcrumbLabel !== '') {
            return $page->breadcrumbLabel;
        }

        $boundLabel = $this->labelFromBindings($page, $parameters);
        if ($boundLabel !== null) {
            return $boundLabel;
        }

        if ($page->heading !== '') {
            return $page->heading;
        }

        if ($page->title !== '') {
            return $page->title;
        }

        return Str::of($page->name)->afterLast('.')->replace(['-', '_'], ' ')->headline()->toString();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function labelFromBindings(PageDefinition $page, array $parameters): ?string
    {
        $bindings = $page->breadcrumbBindings;

        if ($bindings === [] && $page instanceof DetailsPageDefinition) {
            $primary = $this->primaryRouteParameter($page);
            if ($primary !== null && array_key_exists($primary, $parameters)) {
                return $this->labelForModel($parameters[$primary]);
            }
        }

        foreach ($bindings as $param => $modelClass) {
            if (! array_key_exists($param, $parameters)) {
                continue;
            }

            $value = $parameters[$param];

            if ($value instanceof Model) {
                return $this->labelForModel($value);
            }

            if (! class_exists($modelClass)) {
                continue;
            }

            $model = $modelClass::find($value);
            if ($model !== null) {
                return $this->labelForModel($model);
            }
        }

        return null;
    }

    protected function primaryRouteParameter(PageDefinition $page): ?string
    {
        if (preg_match_all('/\{([^}:]+)(?::[^}]+)?\}/', $page->route, $matches) === false) {
            return null;
        }

        $params = $matches[1] ?? [];

        return $params === [] ? null : $params[count($params) - 1];
    }

    protected function labelForModel(mixed $value): string
    {
        if ($value instanceof BreadcrumbLabel) {
            return $value->cmsBreadcrumbLabel();
        }

        if ($value instanceof Model) {
            foreach (['name', 'title', 'label'] as $attr) {
                $candidate = $value->getAttribute($attr);
                if (is_string($candidate) && $candidate !== '') {
                    return $candidate;
                }
            }

            return (string) $value->getKey();
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function safeUrl(string $routeName, array $parameters = []): ?string
    {
        try {
            return $this->registry->urlForRoute($routeName, $this->parametersForRoute($routeName, $parameters));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function parametersForRoute(string $routeName, array $parameters): array
    {
        $route = Route::getRoutes()->getByName($routeName);

        if ($route === null) {
            return $parameters;
        }

        $routeParameters = array_flip($route->parameterNames());

        return array_intersect_key($parameters, $routeParameters);
    }

    /**
     * @param  Crumb[]  $crumbs
     * @return Crumb[]
     */
    protected function dedupe(array $crumbs): array
    {
        $result = [];
        $previous = null;

        foreach ($crumbs as $crumb) {
            if ($previous !== null && $this->normaliseLabel($previous->label) === $this->normaliseLabel($crumb->label)) {
                if ($crumb->current) {
                    $previous->current = true;
                }
                if ($previous->href === null && $crumb->href !== null) {
                    $previous->href = $crumb->href;
                }

                continue;
            }

            $result[] = $crumb;
            $previous = $crumb;
        }

        return $result;
    }

    protected function normaliseLabel(string $label): string
    {
        return Str::of($label)->lower()->squish()->toString();
    }
}
