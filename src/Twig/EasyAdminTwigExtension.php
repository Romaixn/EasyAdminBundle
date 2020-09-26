<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Twig;

use EasyCorp\Bundle\EasyAdminBundle\Router\CrudUrlBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Router\CrudUrlGenerator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Defines the filters and functions used to render the bundle's templates.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class EasyAdminTwigExtension extends AbstractExtension
{
    private $crudUrlGenerator;
    /** @var TranslatorInterface|null */
    private $translator;

    public function __construct(CrudUrlGenerator $crudUrlGenerator, ?TranslatorInterface $translator)
    {
        $this->crudUrlGenerator = $crudUrlGenerator;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('ea_url', [$this, 'getCrudUrlBuilder']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        $filters = [
            new TwigFilter('ea_flatten_array', [$this, 'flattenArray']),
            new TwigFilter('ea_filesize', [$this, 'fileSize']),
            new TwigFilter('ea_apply_filter_if_exists', [$this, 'applyFilterIfExists'], ['needs_environment' => true]),
        ];

        if (Kernel::VERSION_ID >= 40200) {
            $filters[] = new TwigFilter('transchoice', [$this, 'transchoice']);
        }

        return $filters;
    }

    /**
     * Transforms ['a' => 'foo', 'b' => ['c' => ['d' => 7]]] into ['a' => 'foo', 'b[c][d]' => 7]
     * It's useful to submit nested arrays (e.g. query string parameters) as form fields.
     */
    public function flattenArray($array, $parentKey = null)
    {
        $flattenedArray = [];

        foreach ($array as $flattenedKey => $value) {
            $flattenedKey = null !== $parentKey ? sprintf('%s[%s]', $parentKey, $flattenedKey) : $flattenedKey;

            if (\is_array($value)) {
                $flattenedArray = array_merge($flattenedArray, $this->flattenArray($value, $flattenedKey));
            } else {
                $flattenedArray[$flattenedKey] = $value;
            }
        }

        return $flattenedArray;
    }

    public function fileSize(int $bytes): string
    {
        $size = ['B', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];
        $factor = (int) floor(log($bytes) / log(1024));

        return (int) ($bytes / (1024 ** $factor)).@$size[$factor];
    }

    // Code adapted from https://stackoverflow.com/a/48606773/2804294 (License: CC BY-SA 3.0)
    public function applyFilterIfExists(Environment $environment, $value, string $filterName, ...$filterArguments)
    {
        if (false === $filter = $environment->getFilter($filterName)) {
            return $value;
        }

        return $filter->getCallable()($value, ...$filterArguments);
    }

    public function getCrudUrlBuilder(array $queryParameters = []): CrudUrlBuilder
    {
        return $this->crudUrlGenerator->build($queryParameters);
    }

    /**
     * TODO: Remove this filter when the Symfony's requirement is equal or greater than 4.2
     * and use the built-in trans filter instead with a %count% parameter.
     */
    public function transchoice($message, $count, array $arguments = [], $domain = null, $locale = null)
    {
        if (null === $this->translator) {
            return strtr($message, $arguments);
        }

        return $this->translator->trans($message, array_merge(['%count%' => $count], $arguments), $domain, $locale);
    }
}
