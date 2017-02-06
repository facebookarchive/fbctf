<?hh

class PasswordTypesTest extends FBCTFTest {

  public function testAll(): void {
    $all = HH\Asio\join(Configuration::genAllPasswordTypes());
    $this->assertEquals(4, count($all));

    $p = $all[0];
    $this->assertTrue((bool)preg_match($p->getValue(), 'a'));

    $p = $all[1];
    $this->assertTrue((bool)preg_match($p->getValue(), 'password1'));

    $p = $all[2];
    $this->assertTrue((bool)preg_match($p->getValue(), 'Password1'));

    $p = $all[3];
    $this->assertTrue((bool)preg_match($p->getValue(), 'Pas$word1'));
  }
}
