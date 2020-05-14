<?php


namespace test;


use Phore\ObjectStore\ObjectStore;
use PHPUnit\Framework\TestCase;

class ObjectStoreObjectTest extends TestCase
{


    /**
     * @var ObjectStore
     */
    public $os;

    protected function setUp(): void
    {
        $this->os = ObjectStore::Connect("file://tmp/");
    }


    public function testMetaRead()
    {
        $this->os->object("test")->setMeta(["meta1"=>"val1"]);
        $this->assertEquals(["meta1"=>"val1"], $this->os->object("test")->getMeta());

        // Test also the pluck function
        $this->assertEquals("val1", $this->os->object("test")->getMeta("meta1"));

        // Test the default
        $this->assertEquals(null, $this->os->object("test")->getMeta("meta2", null));


    }

}
