<?php
namespace Data;

final class DBLPLoader implements IXMLLoader
{

    private const repo = "https://dblp.org/xml/release/";

    private ?\XMLReader $xmlReader = null;

    private string $group;

    private \Args\ObjectArgs $oargs;

    public int $conf_seed;

    public string $conf_xml;

    public string $conf_dtd;

    public function __construct(array $dataSets, array $config)
    {
        $this->group = \DataSets::groupOf($dataSets);

        $oa = $this->oargs = (new \Args\ObjectArgs($this))-> //
        setPrefix('conf_')-> //
        mapKeyToProperty(fn ($k) => \str_replace('.', '_', $k));
        $oa->updateAndShift($config);
        $oa->checkEmpty($config);
    }

    public function __destruct()
    {
        $this->closeXMLReader();
    }

    private const unwind = [
        'dblp.*'
    ];

    public function getUnwindConfig(): array
    {
        return self::unwind;
    }

    public function getUnwinding(): \Generator\IUnwinding
    {
        return new \Generator\SimpleUnwinding();
    }

    private const is_list = [
        'author',
        'cite',
        'crossref',
        'cdrom',
        'editor',
        'ee',
        'isbn',
        'note',
        'multipublisher',
        'multiseries',
        'multititle',
        'pages',
        'school',
        'url',
        'multiyear'
    ];

    private const isObject = [
        'author',
        'cite',
        'editor',
        'ee',
        'isbn',
        'note',
        'publisher',
        'series',
        'url'
    ];

    private const isText = [
        'title',
        'address',
        'cdrom',
        'chapter',
        'crossref',
        'month',
        'number',
        'pages',
        'publnr',
        'school',
        'volume',
        'year'
    ];

    private const isMultipliable = [
        'publisher',
        'series',
        'title',
        'year'
    ];

    private const getOut = [
        'title' => '@bibtex'
    ];

    function getOut(string $name, string $subVal): bool
    {
        return (self::getOut[$name] ?? null) === $subVal;
    }

    function isObject(string $name): bool
    {
        return \in_array($name, self::isObject);
    }

    function isText(string $name): bool
    {
        return \in_array($name, self::isText);
    }

    function isMultipliable(string $name): bool
    {
        return \in_array($name, self::isMultipliable);
    }

    function isList(string $name): bool
    {
        return \in_array($name, self::is_list);
    }

    public function deleteXMLFile(): bool
    {
        $ret = false;

        if (! $this->_deleteXMLFile("$this->conf_xml.xml"))
            $ret = false;

        if (! $this->_deleteXMLFile("$this->conf_dtd.dtd"))
            $ret = false;

        return true;
    }

    private function _deleteXMLFile(string $file): bool
    {
        if (! \is_file($file))
            return true;

        return \unlink($file);
    }

    private function closeXMLReader()
    {
        if (null !== $this->xmlReader) {
            $this->xmlReader->close();
            $this->xmlReader = null;
        }
    }

    public function getXMLReader(): \XMLReader
    {
        \wdPush(\DataSets::getGroupPath($this->group));
        $xmlPath = "$this->conf_xml.xml";

        $this->downloadFile("compress.zlib://" . self::repo . "$xmlPath.gz", $xmlPath);
        $this->downloadFile(self::repo . "$this->conf_dtd.dtd", "$this->conf_dtd.dtd");
        $reader = \XMLReader::open($xmlPath);
        $reader->setParserProperty(\XMLReader::LOADDTD, true);
        $reader->setParserProperty(\XMLReader::SUBST_ENTITIES, true);
        \wdPop();

        return $reader;
    }

    private function downloadFile(string $from, string $to): void
    {
        $to = $to ?? $from;

        if (! \is_file($to)) {
            echo "Downloading $from into $to\n";

            if (! \copy($from, $to)) {
                @\unlink($to);
                throw new \Exception("An error occured");
            }
        }
    }

    public function getLabelReplacerForDataSet(\DataSet $dataSet): ?callable
    {
        return LabelReplacer::getReplacerForDataSet($dataSet, $this->conf_seed);
    }
}
