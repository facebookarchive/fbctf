<?hh

class ConfigurationTest extends FBCTFTest {

  public function testAllConfiguration(): void {
    $all = HH\Asio\join(Configuration::genAllConfiguration());
    $this->assertEquals(6, count($all));

    $c = $all[0];
    $this->assertEquals(1, $c->getId());
    $this->assertEquals('field', $c->getField());
    $this->assertEquals('value', $c->getValue());
    $this->assertEquals('description', $c->getDescription());
  }

  public function testValidField(): void {
    $valid = HH\Asio\join(Configuration::genValidField('field'));
    $this->assertTrue($valid);
    $valid = HH\Asio\join(Configuration::genValidField('no'));
    $this->assertFalse($valid);
  }
}
