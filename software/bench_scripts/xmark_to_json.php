
<?php
include_once __DIR__ . '/xmark_to_json/XMark2Json.php';
include_once __DIR__ . '/common/functions.php';
include_once __DIR__ . '/classes/DataSet.php';
include_once __DIR__ . '/mongoimport/MongoImport.php';

\array_shift($argv);

$cmdArgsDef = [
    'clean' => false,
    'load' => false,
    'post-clean' => false,
    'generate' => true,
    'simplify.object' => false,
    'simplify.object.useConfig' => true
];

while (! empty($argv)) {
    $cmdParsed = \parseArgvShift($argv, ';') + $cmdArgsDef;
    $dataSets = \array_filter($cmdParsed, 'is_int', ARRAY_FILTER_USE_KEY);

    if (\count($dataSets) == 0) {
        echo "Convert ALL dataSets to json\n\n";

        foreach (DataSet::getAllGroups() as $group) {
            $toProcess[] = new DataSet($group);
        }
    } else {
        foreach ($dataSets as $dataSet) {
            $ds = new DataSet($dataSet);
            $group = $ds->getGroup();
            $rules = $ds->getRules();

            $ds_top = $toProcess[$group] ?? $ds;

            $rules_top = array_unique(array_merge($ds_top->getRules(), $rules));
            $ds_top->setRules($rules_top);
            $toProcess[$group] = $ds_top;
        }
    }
}

foreach ($toProcess as $dataSet) {

    $simplifyObject = $cmdParsed['simplify.object'];

    if ($simplifyObject) {
        $useConfig = $cmdParsed['simplify.object.useConfig'];
        $forceSimplify = $useConfig ? (include __DIR__ . '/xmark_to_json/do_not_simplify.php') : [];
        $qualifiers = $simplifyObject ? [
            $useConfig ? 'simplified' : 'simplified.all'
        ] : [];
    } else {
        $forceSimplify = [];
        $qualifiers = [];
    }
    $dataSet->setQualifiers($qualifiers);
    $dataSetId = $dataSet->getId();
    echo "\n<$dataSetId>\n";
    $converter = (new \XMark2Json($dataSet))->simplifyObject($simplifyObject, $forceSimplify);

    if ($cmdParsed['generate']) {
        $method = $cmdParsed['clean'] ? 'clean' : 'convert';
        $converter->$method();
    }

    if ($cmdParsed['load']) {
        MongoImport::importDataSet($dataSet);

        if ($cmdParsed['post-clean'])
            $converter->clean();
    }
}