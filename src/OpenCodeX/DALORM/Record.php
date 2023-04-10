<?php

namespace OpenCodeX\DALORM;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;

trait Record
{
    protected ?EntityRepository $repository;
    protected ?EntityManagerInterface $entityManager;

    public function setRepository(EntityRepository $repository): static
    {
        $this->repository = $repository;

        return $this;
    }

    public function setEntityManager(EntityManagerInterface $entityManager): static
    {
        $this->entityManager = $entityManager;

        return $this;
    }

    protected function getPrimaryCriteriaContext(): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->id));
        $context = Context::createDefaultContext();

        return [$criteria, $context];
    }

    public function gets(int|string|array $data)
    {
        if (is_scalar($data)) {
            $this->id = $data;
        } else {
            throw new \Exception('gets not implemented');
        }

        [$criteria, $context] = $this->getPrimaryCriteriaContext();

        $data = $this->repository->search($criteria, $context)
            ->getEntities()
            ->first()
            ->all();

        $record = (new static())->setRepository($this->repository);
        $record->mergeToData($data);

        return $record;
    }

    public function mergeToData(array $data)
    {
        foreach ($data as $key => $val) {
            $this->{$key} = $val;
        }

        return $this;
    }

    public function update(array $data = [])
    {
        [$criteria, $context] = $this->getPrimaryCriteriaContext();

        $productId = $this->repository
            ->searchIds($criteria, $context)
            ->firstId();

        $this->repository->update([
            [
                'id' => $productId,
            ] + $data
        ], $context);

        return $this->mergeToData($data);
    }

    public function insert(array $data): static
    {
        [$criteria, $context] = $this->getPrimaryCriteriaContext();
        $id = Uuid::randomHex();

        $fullData = [
            [
                'id' => $id,
            ] + $data
        ];
        $this->repository->create($fullData, $context);

        return (new static())
            ->setRepository($this->repository)
            ->mergeToData($fullData[0]);
    }

    public function delete(): static
    {
        [$criteria, $context] = $this->getPrimaryCriteriaContext();

        $id = $this->repository
            ->searchIds($criteria, $context)
            ->firstId();

        $this->repository->delete([
            [
                'id' => $id
            ]
        ], $context);

        return $this;
    }
}
