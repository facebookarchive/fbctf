<?hh

class ScoreLogTest extends FBCTFTest {

  public function testAll(): void {
    $all = HH\Asio\join(ScoreLog::genAllScores());
    $this->assertEquals(2, count($all));

    $s = $all[0];
    $this->assertEquals(1, $s->getId());
    $this->assertEquals(2, $s->getLevelId());
    $this->assertEquals(1, $s->getTeamId());
    $this->assertEquals(10, $s->getPoints());
    $this->assertEquals('2016-04-24 17:15:23', $s->getTs());

    $s = $all[1];
    $this->assertEquals(2, $s->getId());
    $this->assertEquals(3, $s->getLevelId());
    $this->assertEquals(1, $s->getTeamId());
    $this->assertEquals(15, $s->getPoints());
    $this->assertEquals('2016-04-24 17:15:23', $s->getTs());

    $all = HH\Asio\join(ScoreLog::genAllScoresByTeam(2));
    $this->assertEquals(0, count($all));
    $all = HH\Asio\join(ScoreLog::genAllScoresByTeam(1));
    $this->assertEquals(2, count($all));
    $all = HH\Asio\join(ScoreLog::genAllScoresByType('flag'));
    $this->assertEquals(1, count($all));
  }

  public function testReset(): void {
    HH\Asio\join(ScoreLog::genResetScores());
    $all = HH\Asio\join(ScoreLog::genAllScores());
    $this->assertEquals(0, count($all));
  }

  public function testPreviousScore(): void {
    $previous = HH\Asio\join(ScoreLog::genPreviousScore(
      2, // level
      1, // team
      false, // any_team
    ));
    $this->assertTrue($previous);
    $previous = HH\Asio\join(ScoreLog::genPreviousScore(
      2, // level
      1, // team
      true, // any_team
    ));
    $this->assertFalse($previous);
  }

  public function testDuplicateLogValidScore(): void {
    HH\Asio\join(ScoreLog::genLogValidScore(
      2, // level
      1, // team
      5, // points
      'base', // any_team
    ));

    $all = HH\Asio\join(ScoreLog::genAllScores());
    $this->assertEquals(2, count($all));
    $s = $all[0];
    $this->assertEquals(10, $s->getPoints());
  }

  public function testLogValidScore(): void {
    HH\Asio\join(ScoreLog::genLogValidScore(
      1, // level
      1, // team
      5, // points
      'base', // any_team
    ));

    $all = HH\Asio\join(ScoreLog::genAllScores());
    $this->assertEquals(3, count($all));
    $s = $all[0];
    $this->assertEquals(5, $s->getPoints());
  }
}
