<?hh

class LogoTest extends FBCTFTest {

  public function testAllLogos(): void {
    $all = HH\Asio\join(Logo::genAllLogos());
    $all = $all->toArray();
    $this->assertEquals(1, count($all));

    $l = array_pop($all);
    $this->assertEquals(1, $l->getId());
    $this->assertEquals('name', $l->getName());
    $this->assertEquals('logo_path', $l->getLogo());
    $this->assertTrue($l->getUsed());
    $this->assertTrue($l->getEnabled());
    $this->assertFalse($l->getProtected());
  }

  public function testCheckExists(): void {
    $this->assertTrue(
      HH\Asio\join(Logo::genCheckExists('name')),
    );
    $this->assertFalse(
      HH\Asio\join(Logo::genCheckExists('no')),
    );
  }

  public function testSetEnabled(): void {
    HH\Asio\join(Logo::genSetEnabled(1, false));
    $this->assertFalse(
      HH\Asio\join(Logo::genCheckExists('name')),
    );
  }

  public function testRandom(): void {
    $s = HH\Asio\join(Logo::genRandomLogo());
    $this->assertNotNull($s);
  }
}
