<?php
namespace Test;

abstract class AbstractTest
{

    protected array $errors = [];

    protected \DataSet $ds;

    protected array $partitions;

    protected CmdArgs $cmdParser;

    protected \DBImport\IDBImport $dbImport;

    private \Data\IJsonLoader $jsonLoader;

    public abstract function execute();

    public function __construct(\DataSet $ds, CmdArgs $cmdParser, \Data\IPartition ...$partitions)
    {
        $this->ds = $ds;
        $this->partitions = $partitions;

        if (! empty($collection) && ! \in_array($collectionName, $ds->getCollections()))
            throw new \Exception("$ds does not have the collection $collectionName");

        $this->cmdParser = $cmdParser;
        $this->dbImport = \DBImports::get($cmdParser, $ds);
        $this->jsonLoader = $ds->getJsonLoader();
    }

    public final function getCollectionsName(): array
    {
        return \array_unique(\array_map(fn ($p) => \Data\Partitions::getCollectionName($this->ds, $p), $this->partitions));
    }

    public final function collectionsExists(): bool
    {
        return $this->dbImport->collectionsExists($this->getCollectionsName());
    }

    public final function loadIndex(string $indexName): void
    {
        foreach ($this->getCollectionsName() as $coll)
            $this->dbImport->createIndex($coll, $indexName);
    }

    public final function loadCollections(): void
    {
        $this->jsonLoader->generateJson();

        if ($this->cmdParser['args']['write-all-partitions'])
            $this->dbImport->importDataset($this->ds);
        else
            $this->dbImport->importCollections($this->ds, $this->getCollectionsName());
    }

    public final function reportErrors(?array $errors = null): void
    {
        $errors = $errors ?? $this->errors;

        if (empty($errors))
            return;

        \ob_start();
        echo "\n== Error reporting ==\n\n";

        foreach ($errors as $err) {
            $collections = \implode(',', $err['collections']);
            echo "= {$err['dataset']}/{$collections} =\n{$err['exception']->getMessage()}\n{$err['exception']->getTraceAsString()}\n\n";
        }
        \fwrite(STDERR, \ob_get_clean());
    }

    public final function getErrors(): array
    {
        return $this->errors;
    }
}