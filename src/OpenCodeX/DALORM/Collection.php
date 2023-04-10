<?php

namespace OpenCodeX\DALORM;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

trait Collection
{
    protected ?Criteria $criteria = null;

    protected ?EntityRepositoryInterface $repository = null;

    public function setRepository(EntityRepositoryInterface $repository): static
    {
        $this->repository = $repository;

        return $this;
    }
    public function prepareCriteria()
    {
        if (!$this->criteria) {
            $this->criteria = new Criteria();
        }

        return $this->criteria;
    }

    protected function getContext(): Context
    {
        return new Context(new SystemSource());
    }

    public function where(string $field, string|int|float|bool|null $value, string $sign = '='): self
    {
        $criteria = $this->prepareCriteria();

        if (is_null($value)) {
            if (!in_array($sign, ['=', '!='])) {
                throw new \Exception('Only = and != is supported by NULL value');
            }

            if ($sign === '=') {
                $criteria->addFilter(
                    new EqualsFilter($field, null)
                );
            } else {
                $criteria->addFilter(
                    new NotFilter(
                        NotFilter::CONNECTION_AND,
                        [
                            new EqualsFilter($field, null),
                        ]
                    )
                );
            }
        } else if ($sign === '=') {
            $criteria->addFilter(
                new EqualsFilter($field, $value)
            );
        } else {
            throw new \Exception('Filter ' . $sign . ' not implemented');
        }

        return $this;
    }

    public function one() /*: Entity*/
    {
        $this->prepareCriteria()->setLimit(1);

        return $this->all()[0];
    }

    public function all(): array
    {
        return array_values($this->repository
            ->search($this->prepareCriteria(), $this->getContext())
            ->getEntities()
            ->map(function (ArrayEntity $e) {
                $data = [];
                $converter = new CamelCaseToSnakeCaseNameConverter();

                $isBinary = function ($value): bool {
                    return false === mb_detect_encoding((string) $value, null, true);
                };
                $STR2UUID = function (string $uuid) {
                    return preg_replace("/([0-9a-f]{8})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{12})/", "$1-$2-$3-$4-$5", $uuid);
                };
                $HEX2UUID = function (string $hex) use ($STR2UUID) {
                    $implodedOriginal = implode(unpack("h*", $hex));
                    $imploded = "";
                    // weird reverse
                    for ($i = 0; $i < 16; $i++) {
                        $imploded = $imploded . $implodedOriginal[($i * 2) + 1] . $implodedOriginal[$i * 2];
                    }
                    // resolve issue with invalid uuid
                    //dd($imploded, $hex);
                    //$imploded = $hex;

                    return preg_replace("/([0-9a-f]{8})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{12})/", "$1-$2-$3-$4-$5", $imploded);
                };

                //if (static::class === \Offset\PageBuilder\EntityCollection\ActionsMorphs::class)
                //    d("original", $e->all());

            foreach ($e->all() as $key => $value) {
                //$normalizer = new ObjectNormalizer(null, );
                $normalizedKey = $converter->normalize($key);
                $data[$normalizedKey] = is_object($value)
                    ? $value->format('Y-m-d H:i:s')
                    : ($isBinary($value) ? /*$HEX2UUID($value)*/bin2hex($value) : $value);

                // binary? - solved with implode()
                /*if (is_array($data[$normalizedKey])) {
                    if (static::class === \Offset\PageBuilder\EntityCollection\ActionsMorphs::class)
                    d($normalizedKey);
                    $data[$normalizedKey] = $data[$normalizedKey][array_keys($data[$normalizedKey])[0]];
                }*/
            }

                // transform to uu-id
            if (isset($data['id']) && strlen($data['id']) === 32) {
                // this is causing issues with routing and uuid transformation
                //$data['id'] = $STR2UUID($data['id']);
            }

                //if (static::class === \Offset\PageBuilder\EntityCollection\ActionsMorphs::class && $data['parent_id'])
                //dd("transformed", $data);
                // array_map(fn($field) => is_object($field) ? $field->format('Y-m-d H:i:s') : $field, $e->all()));
                return $data;
            }));
    }

    public function oneOrFail() /*: Entity*/
    {
        $one = $this->one();

        if ($one) {
            return $one;
        }

        throw new \Exception('Data (one) not found in ' . static::class);
    }

    public function allOrFail(): array
    {
        $all = $this->all();

        if ($all) {
            return $all;
        }

        throw new \Exception('Data (all) not found in ' . static::class);
    }
}
