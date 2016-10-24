<?hh

class MultiTeamTest extends FBCTFTest {

  public function testAll(): void {
    $all = HH\Asio\join(MultiTeam::genAllTeams());
    $this->assertEquals(1, count($all));

    //test parent class methods
    $t = $all[0];
    $this->assertEquals(1, $t->getId());
    $this->assertTrue($t->getActive());
    $this->assertFalse($t->getAdmin());
    $this->assertFalse($t->getProtected());
    $this->assertTrue($t->getVisible());
    $this->assertEquals('name', $t->getName());
    $this->assertEquals('password_hash', $t->getPasswordHash());
    $this->assertEquals(10, $t->getPoints());
    $this->assertEquals('2015-04-26 12:14:20', $t->getLastScore());
    $this->assertEquals('logo', $t->getLogo());
    $this->assertEquals('2015-04-26 12:14:20', $t->getCreatedTs());

    //test single team using multiteam cache
    $t = HH\Asio\join(MultiTeam::genTeam(1));
    $this->assertEquals(1, $t->getId());
    $this->assertTrue($t->getActive());
    $this->assertFalse($t->getAdmin());
    $this->assertFalse($t->getProtected());
    $this->assertTrue($t->getVisible());
    $this->assertEquals('name', $t->getName());
    $this->assertEquals('password_hash', $t->getPasswordHash());
    $this->assertEquals(10, $t->getPoints());
    $this->assertEquals('2015-04-26 12:14:20', $t->getLastScore());
    $this->assertEquals('logo', $t->getLogo());
    $this->assertEquals('2015-04-26 12:14:20', $t->getCreatedTs());

    //test single team using multiteam leaderboard cache
    $all = HH\Asio\join(MultiTeam::genLeaderboard());
    $this->assertEquals(1, count($all));
    $t = $all[0];
    $this->assertEquals(1, $t->getId());
    $this->assertTrue($t->getActive());
    $this->assertFalse($t->getAdmin());
    $this->assertFalse($t->getProtected());
    $this->assertTrue($t->getVisible());
    $this->assertEquals('name', $t->getName());
    $this->assertEquals('password_hash', $t->getPasswordHash());
    $this->assertEquals(10, $t->getPoints());
    $this->assertEquals('2015-04-26 12:14:20', $t->getLastScore());
    $this->assertEquals('logo', $t->getLogo());
    $this->assertEquals('2015-04-26 12:14:20', $t->getCreatedTs());

    //test points from multiteam cache
    $t = HH\Asio\join(MultiTeam::genPointsByType(1, 'flag'));
    $this->assertEquals(10, $t);

    //test single team using multiteam active team cache
    $all = HH\Asio\join(MultiTeam::genAllActiveTeams());
    $this->assertEquals(1, count($all));
    $t = $all[0];
    $this->assertEquals(1, $t->getId());
    $this->assertTrue($t->getActive());
    $this->assertFalse($t->getAdmin());
    $this->assertFalse($t->getProtected());
    $this->assertTrue($t->getVisible());
    $this->assertEquals('name', $t->getName());
    $this->assertEquals('password_hash', $t->getPasswordHash());
    $this->assertEquals(10, $t->getPoints());
    $this->assertEquals('2015-04-26 12:14:20', $t->getLastScore());
    $this->assertEquals('logo', $t->getLogo());
    $this->assertEquals('2015-04-26 12:14:20', $t->getCreatedTs());

    //test single team using multiteam visible team cache
    $all = HH\Asio\join(MultiTeam::genAllVisibleTeams());
    $this->assertEquals(1, count($all));
    $t = $all[0];
    $this->assertEquals(1, $t->getId());
    $this->assertTrue($t->getActive());
    $this->assertFalse($t->getAdmin());
    $this->assertFalse($t->getProtected());
    $this->assertTrue($t->getVisible());
    $this->assertEquals('name', $t->getName());
    $this->assertEquals('password_hash', $t->getPasswordHash());
    $this->assertEquals(10, $t->getPoints());
    $this->assertEquals('2015-04-26 12:14:20', $t->getLastScore());
    $this->assertEquals('logo', $t->getLogo());
    $this->assertEquals('2015-04-26 12:14:20', $t->getCreatedTs());

    //test single team using multiteam logo team cache
    $all = HH\Asio\join(MultiTeam::genWhoUses('logo'));
    $this->assertEquals(1, count($all));
    $t = $all[0];
    $this->assertEquals(1, $t->getId());
    $this->assertTrue($t->getActive());
    $this->assertFalse($t->getAdmin());
    $this->assertFalse($t->getProtected());
    $this->assertTrue($t->getVisible());
    $this->assertEquals('name', $t->getName());
    $this->assertEquals('password_hash', $t->getPasswordHash());
    $this->assertEquals(10, $t->getPoints());
    $this->assertEquals('2015-04-26 12:14:20', $t->getLastScore());
    $this->assertEquals('logo', $t->getLogo());
    $this->assertEquals('2015-04-26 12:14:20', $t->getCreatedTs());

    //test single team using multiteam level completed cache
    $all = HH\Asio\join(MultiTeam::genCompletedLevel(2));
    $this->assertEquals(1, count($all));
    $t = $all[0];
    $this->assertEquals(1, $t->getId());
    $this->assertTrue($t->getActive());
    $this->assertFalse($t->getAdmin());
    $this->assertFalse($t->getProtected());
    $this->assertTrue($t->getVisible());
    $this->assertEquals('name', $t->getName());
    $this->assertEquals('password_hash', $t->getPasswordHash());
    $this->assertEquals(10, $t->getPoints());
    $this->assertEquals('2015-04-26 12:14:20', $t->getLastScore());
    $this->assertEquals('logo', $t->getLogo());
    $this->assertEquals('2015-04-26 12:14:20', $t->getCreatedTs());

    //test single team using multiteam first capture cache
    $t = HH\Asio\join(MultiTeam::genFirstCapture(2));
    $this->assertEquals(1, $t->getId());
    $this->assertTrue($t->getActive());
    $this->assertFalse($t->getAdmin());
    $this->assertFalse($t->getProtected());
    $this->assertTrue($t->getVisible());
    $this->assertEquals('name', $t->getName());
    $this->assertEquals('password_hash', $t->getPasswordHash());
    $this->assertEquals(10, $t->getPoints());
    $this->assertEquals('2015-04-26 12:14:20', $t->getLastScore());
    $this->assertEquals('logo', $t->getLogo());
    $this->assertEquals('2015-04-26 12:14:20', $t->getCreatedTs());
  }
}
  