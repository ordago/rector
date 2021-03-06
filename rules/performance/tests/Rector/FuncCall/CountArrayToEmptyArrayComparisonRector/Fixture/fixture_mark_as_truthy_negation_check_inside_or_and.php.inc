<?php

namespace Rector\Performance\Tests\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector\Fixture;

class SomeClassMarkTruthyInsideOrAnd
{
    public function run()
    {
        $data = [];
        true || ! count($data);
        ! count($data) || true;
        true && ! count($data);
        ! count($data) && true;
    }
}

?>
-----
<?php

namespace Rector\Performance\Tests\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector\Fixture;

class SomeClassMarkTruthyInsideOrAnd
{
    public function run()
    {
        $data = [];
        true || $data === [];
        $data === [] || true;
        true && $data === [];
        $data === [] && true;
    }
}

?>