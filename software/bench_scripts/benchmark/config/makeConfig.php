<?php

function makeConfig(\DBImport\IDBImport $dbImport, DataSet $dataSet, array $partitions, \Test\CmdArgs &$cmdParser) //
{
    $cmdArg = &$cmdParser['args'];
    $javaProperties = $cmdParser['javaProperties'];
    $group = $dataSet->group();
    $rules = $dataSet->rules();
    $dataSetPath = $dataSet->path();

    $serverConf = \array_filter($cmdArg, fn ($k) => \str_starts_with($k, 'server.'), ARRAY_FILTER_USE_KEY);

    $cmd = $cmdArg['cmd'];
    $cold = $cmdArg['cold'];

    if ($rules === 'original') {
        $hasRules = false;
        $native = '';
    } else {
        $hasRules = true;
        $native = $cmdArg['native'] ?? '';
    }
    $hasNative = ! empty($native);

    $common = (include __DIR__ . '/common.php');
    $basePath = getBenchmarkBasePath();

    if ($dataSet->isSimplified()) {

        if (null === $cmdArg['toNative_summary'])
            $cmdArg['toNative_summary'] = 'label';
    } elseif (null === $cmdArg['toNative_summary'])
        $cmdArg['toNative_summary'] = $cmdArg['summary'];

    // Force the summary to be at least label for query traduction
    if ($cmdArg['toNative_summary'] == 'depth')
        $cmdArg['toNative_summary'] = 'label';

    $partitionID = $dataSet->pidKey();
    $filterStrPrefix = $javaProperties['summary.filter.stringValuePrefix'];

    $outputDirGenerator = $common['bench.output.dir.generator'];

    $fsummary = function ($baseDir, $summPrefix, $summType, $filterStrPrefix = null) {

        if (empty($filterStrPrefix))
            $filterStrPrefix = "";
        else
            $filterStrPrefix = "-$filterStrPrefix";

        return "$baseDir/{$summPrefix}summary-$summType$filterStrPrefix.txt";
    };
    $cmdIsPartition = $cmd === 'partition';

    $fpartition = function ($partition) use ($cmdIsPartition, $partitionID): string {
        $range = $cmdIsPartition ? '' : $partition->getLogicalRange($partitionID);

        if (empty($range))
            $range = '';
        else {
            $range = implode('..', $range);
            $range = ";[$range]";
        }
        return $partition->getID() . ';' . $partition->getPrefix() . $range;
    };

    if ($cmdArg['parallel']) {

        if (! \is_array($partitions))
            throw new \Exception("In parallel mode \$partitions must be an array; have " . print_r($partitions, true));

        $cprefix = "";
        $javaCollection = [];
        $javaSummary = [];
        $benchSummary = [];
        $javaToNativeSummary = [];
        $javaPartition = [];
        $benchPartition = [];
        $noSummary = empty($cmdArg['summary']);

        foreach ($partitions as $partition) {
            $partID = $partition->getID();
            $cprefix = empty($partID) ? '' : "$partID-";

            $javaCollection[] = \Data\Partitions::getCollectionName($dataSet, $partition);

            if ($noSummary) {
                $javaSummary[] = $benchSummary[] = '';
            } else {
                $javaSummary[] = $fsummary('${dataset.baseDir}', $cprefix, '${summary.type}', $filterStrPrefix);
                $benchSummary[] = $fsummary($dataSetPath, $cprefix, $cmdArg['summary'], $filterStrPrefix);
            }
            $javaToNativeSummary[] = $fsummary('${dataset.baseDir}', $cprefix, '${toNative.summary.type}');
            $benchToNativeSummary[] = $fsummary($dataSetPath, $cprefix, $cmdArg['toNative_summary']);

            if ($partition->isLogical()) {
                $javaPartition[] = $fpartition($partition);
                $benchPartition[] = $partition;
            } else
                $javaPartition[] = '';
        }
        $outDirPattern = $outputDirGenerator($dataSet, \Data\Partitions::noPartition(), $cmdArg, $javaProperties);
    } else {
        //
        // Not parallel: test only one collection
        //
        if (\is_array($partitions) && count($partitions) == 1)
            $partitions = \array_shift($partitions);

        if (! $partitions instanceof \Data\IPartition)
            throw new \Exception("In sequential mode \$collections must be a \Data\IPartition; have " . gettype($partitions));

        $partition = $partitions;
        $benchPartition = [];

        if (false && $partition->isLogical()) {
            $javaPartition = $fpartition($partition);
            $benchPartition = $partition;
        }
        $partID = $partition->getID();

        $cprefix = empty($partID) ? '' : "$partID-";

        $javaCollection = \Data\Partitions::getCollectionName($dataSet, $partition);

        if (empty($cmdArg['summary'])) {
            $javaSummary = '';
            $benchSummary = '';
        } else {
            $javaSummary = $fsummary('${dataset.baseDir}', $cprefix, '${summary.type}', $filterStrPrefix);
            $benchSummary = $fsummary($dataSetPath, $cprefix, $cmdArg['summary'], $filterStrPrefix);
        }

        if (empty($cmdArg['toNative_summary'])) {
            $javaToNativeSummary = '';
            $benchToNativeSummary = '';
        } else {
            $javaToNativeSummary = $fsummary('${dataset.baseDir}', $cprefix, '${toNative.summary.type}');
            $benchToNativeSummary = $fsummary($dataSetPath, $cprefix, $cmdArg['toNative_summary']);
        }
        $outDirPattern = $outputDirGenerator($dataSet, $partition, $cmdArg, $javaProperties);
    }
    $serverConf['server.collection'] = $javaCollection;

    $javaProperties = array_merge([
        'dataset.baseDir' => $dataSetPath,
        'queries.dir' => DataSets::getQueriesBasePath($dataSet->group()),
        'rules' => '',
        'summary' => $javaSummary,
        'summary.type' => $cmdArg['summary'],
        'toNative.summary' => $javaToNativeSummary,
        'toNative.summary.type' => $cmdArg['toNative_summary']
    ], $javaProperties) + $common['java.properties'];

    $outDir = sprintf($outDirPattern, $common['bench.datetime']->format($common['datetime.format']));

    if (! empty($cmdArg['output'] ?? null))
        $pp = $cmdArg['output'];
    else
        $pp = $common['bench.output.base.path']($dataSet, $cmdParser);

    if (! \is_dir($pp))
        \mkdir($pp, 0777, true);

    $bpath = \realpath($pp);

    if (false === $bpath)
        throw new \Exception("Error output path '$pp' does not exists");

    // <<< Java properties updates >>>

    $outputPath = $bpath . "/$outDir";
    $javaProperties['output.path'] = $outputPath;

    // Inhibit summary.filter.types when no summary present
    if (empty($cmdArg['summary']))
        $javaProperties['summary.filter.types'] = 'n';

    // Default value for 'leaf.checkTerminal
    if (null === $javaProperties['leaf.checkTerminal'])
        $javaProperties['leaf.checkTerminal'] = ($javaProperties['summary.filter.types'] === 'n') ? 'y' : 'n';

    if ($cmdArg['parallel'] && $javaProperties['querying.mode'] === 'explain')
        $javaProperties['querying.mode'] = 'explaincolls';

    // Note: for now only prefix partitions are presents
    if (isset($javaPartition)) {
        $pattern = \Data\AbstractPartition::filePattern($partitionID);
        $javaProperties = \array_merge($javaProperties, [
            'partition' => $javaPartition,
            'partition.mode' => 'prefix',
            'partition.output.pattern' => "\${dataset.baseDir}/$pattern"
        ]);
    }


    // <<< >>>

    $testRegex = \preg_quote($outDirPattern);
    $testRegex = \sprintf($testRegex, '[^\[\]]+');
    $testRegex = "#^$testRegex$#";

    if ($cmdArg['skip-existing']) {
        \wdPush($bpath);
        $test_existing = \array_filter(\scandirNoPoints('.'), fn ($n) => \preg_match($testRegex, $n));
        $test_existing = \array_filter($test_existing, fn ($p) => \is_file("$p/@end"));
        \wdPop();
    } else
        $test_existing = null;

    $ret = array_merge($common, [
        'app.cmd' => $cmd,
        'bench.query.native.pattern' => $hasNative ? "$dataSetPath/queries/%s_each-native-$native.txt" : '',
        'bench.cold' => $cold,
        'dataSet' => $dataSet,
        'partition' => $benchPartition,
        'summary' => $benchSummary,
        'toNative.summary' => $benchToNativeSummary,
        'test.existing' => $test_existing,
        'bench.output.dir' => $outDir,
        'bench.output.path' => $outputPath,
        'bench.output.pattern' => $outDirPattern,
        'bench.plot.types' => $cmdArg['plot'],
        'app.output.display' => $cmdArg['cmd-display-output'],
        'timeout.order.queries' => $cmdArg['timeout-order-queries']
    ]);
    $ret['bench.measures']['default']['nb'] = (int) $cmdArg['bench-measures-nb'];

    $ret['java.properties'] = $javaProperties;

    if ($hasRules)
        $ret['java.properties'] = array_merge($ret['java.properties'], [
            'rules' => $dataSet->rulesPath()
        ]);

    $ret['java.properties'] = $dbImport->makeJavaProperties($serverConf) + $ret['java.properties'];
    return $ret;
}
