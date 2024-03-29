<?php
namespace Help;

final class Plotter
{

    private function __construct()
    {
        throw new \Error();
    }

    // ========================================================================
    public static function encodeDataValue($v)
    {
        if (\is_string($v))
            return "\"$v\"";

        return $v;
    }

    public static function elementsFormat(array $elements, array $selection = [
        'parallel',
        'partitioning',
        'summary'
    ])
    {
        $ret = [];

        foreach ($selection as $select) {

            switch ($select) {

                case 'parallel':
                    if ($elements['parallel'])
                        $ret[] = 'parallel';
                    break;

                case 'partitioning':
                    if (empty($elements['partitioning']))
                        break;
                    if ($elements['partitioning'][0] === 'L')
                        $ret[] = 'logical';
                    else
                        $ret[] = 'physical';
                    break;

                case 'summary':
                    $s = $elements['summary'] ?? 'depth';

                    if ($elements['filter_prefix'])
                        $s .= "-{$elements['filter_prefix']}";

                    $ret[] = $s;
                    break;
            }
        }
        return implode(' ', $ret);
    }

    public static function elementsSimpleFormat(array $elements): string
    {
        return self::elementsFormat($elements);
    }

    public static function readJavaProperties($stream)
    {
        if (is_string($stream)) {
            $s = $stream;
            $stream = \fopen('php://memory', 'r+');
            fwrite($stream, $s);
            rewind($stream);
        }

        $ret = [];
        while (false !== ($line = \fgets($stream))) {
            $line = \trim($line);
            if (empty($line))
                continue;
            $parts = \explode('=', $line, 2);

            $ret[$parts[0]] = \trim($parts[1]);
        }
        \fclose($stream);
        return $ret;
    }

    public static function encodeDirNameElements(array $elements, string $replacement = null)
    {
        $fullPattern = $elements['full_pattern'] ?? null;
        $group = $elements['group'];
        $theRules = $elements['rules'] ?? null;
        $qualifiers = $elements['qualifiers'] ?? null;

        if (isset($elements['full_partition']))
            $coll = '.' . $elements['full_partition'];
        else {
            $pid = $elements['partitioning'] ?? '';
            $coll = empty($pid) ? '' : ".$pid";
            $pid = $elements['partition'] ?? '';
            $coll .= empty($pid) ? '' : ".$pid";
        }
        if (! empty($coll) && ($pid = $elements['partition_id'] ?? null) && $pid !== 'pid')
            $coll .= "-$pid";

        $outDir = "[$group$coll][$theRules][$qualifiers]";
        $outDir .= '%s';

        if ($elements['parallel'] ?? false)
            $outDir .= '[parall]';

        if ($summary = $elements['summary'] ?? null)
            $outDir .= "[summary-{$summary}]";
        if ($elements['filter_types'] ?? false)
            $outDir .= '[filter-types]';
        if ($i = $elements['filter_prefix'] ?? 0)
            $outDir .= "[filter-prefix-$i]";
        if ($summary = $elements['toNative'] ?? null)
            $outDir .= "[toNative-{$elements['toNative']}]";

        if (null !== $replacement)
            $outDir = \sprintf($outDir, $replacement);

        return $outDir;
    }

    public static function extractDirNameElements(string $dirName)
    {
        $ret = [
            'group' => null,
            'partitioning' => null,
            'partition' => null,
            'rules' => null,
            'qualifiers' => null,
            'summary' => null,
            'toNative' => null,
            'parallel' => false,
            'full_group' => null,
            'full_partition' => null,
            'partition_id' => null,
            'filter_types' => false,
            'filter_prefix' => null,
            'time' => null
        ];

        \preg_match("#^\[((.+)(?:\.(.+)(?:-(.+))?)?)\]\[(.*)\]\[(.*)\]#U", $dirName, $matches);
        $ret['full_group'] = $matches[1] ?? null;
        $ret['group'] = $matches[2] ?? null;
        $ret['full_partition'] = $matches[3] ?? null;
        $ret['partition_id'] = $matches[4] ?? null;
        $ret['rules'] = $matches[5] ?? null;
        $ret['qualifiers'] = $matches[6] ?? null;
        $ret['full_pattern'] = \preg_replace('#\[(\d\d\d\d-\d\d-\d\d.+)\]#U', '}[%s]', $dirName);

        if (\preg_match('#\[(\d\d\d\d-\d\d-\d\d.+)\]#U', $dirName, $matches))
            $ret['time'] = $matches[1];

        \preg_match("#\[filter-prefix-(\d+)\]#U", $dirName, $matches);
        $ret['filter_prefix'] = $matches[1] ?? null;

        list ($ret['partitioning'], $ret['partition']) = explode('.', $ret['full_partition'], 2) + [
            null,
            null
        ];
        if (\preg_match("#\[filter-types\]#U", $dirName, $matches))
            $ret['filter_types'] = $matches[1] ?? null;

        if (\preg_match("#\[summary-(.+)\]#U", $dirName, $matches))
            $ret['summary'] = $matches[1] ?? null;

        if (\preg_match("#\[toNative-(.+)\]#U", $dirName, $matches))
            $ret['toNative'] = $matches[1] ?? null;

        if (\preg_match("#\[parall]#U", $dirName))
            $ret['parallel'] = true;

        return $ret;
    }
}
