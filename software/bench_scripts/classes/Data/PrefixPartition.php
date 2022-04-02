<?php
namespace Data;

final class PrefixPartition extends PhysicalPartition
{

    private string $prefix_s;

    private array $prefix;

    private string $cname;

    public function __construct(\DataSet $ds, string $collectionName, string $id, string $prefix, ?IPartitioning $logical = null)
    {
        parent::__construct($id, '', $logical);
        $this->cname = $collectionName;

        $this->prefix = \explode('.', $prefix);
        $this->prefix_s = $prefix;
    }

    public function getPrefix(): string
    {
        return $this->prefix_s;
    }

    public function getCollectionName(): string
    {
        return $this->cname;
    }

    public function contains(array $data): bool
    {
        $noPrefix = (object) null;
        $f = \array_follow($data, $this->prefix, $noPrefix);
        return $f !== $noPrefix;
    }
}