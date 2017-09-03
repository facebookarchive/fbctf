<?hh

class LevelTest extends FBCTFTest {

  public function testWhoUses(): void {
    $l = HH\Asio\join(Level::genWhoUses(1));
    $this->assertNotNull($l);
    $this->assertEquals('title', $l?->getTitle());
  }

  public function testCheckStatus(): void {
    $this->assertTrue(HH\Asio\join(Level::genCheckStatus(1)));
  }

  public function testCheckBase(): void {
    $this->assertTrue(HH\Asio\join(Level::genCheckBase(1)));
  }

  public function testCreateLevel(): void {
    $id = HH\Asio\join(Level::genCreate(
      'flag',
      'title 2',
      'description 2',
      2, // entity_id
      3, // category_id
      15, // points
      2, // bonus
      1, // bonus_dec
      1, // bonus_fix
      'flag 2', // flag
      'hint 2', // hint
      2, // penalty
      3, //wrong_answer_penalty,
      1, //is_short_answer (flags are short answer)
      "A", //multiple_choice_1
      "B", //multiple_choice_2
      "C", //multiple_choice_3
      "D", //multiple_choice_4
    ));

    $this->assertEquals(4, $id);
    $all = HH\Asio\join(Level::genAllLevels());
    $this->assertEquals(4, count($all));
    $l = $all[3];
    $this->assertEquals(4, $l->getId());
    $this->assertFalse($l->getActive());
    $this->assertEquals('flag', $l->getType());
    $this->assertEquals('title 2', $l->getTitle());
    $this->assertEquals('description 2', $l->getDescription());
    $this->assertEquals(2, $l->getEntityId());
    $this->assertEquals(3, $l->getCategoryId());
    $this->assertEquals(15, $l->getPoints());
    $this->assertEquals(2, $l->getBonus());
    $this->assertEquals(1, $l->getBonusDec());
    $this->assertEquals(1, $l->getBonusFix());
    $this->assertEquals('flag 2', $l->getFlag());
    $this->assertEquals('hint 2', $l->getHint());
    $this->assertEquals(2, $l->getPenalty());
    $this->assertEquals(3, $l->getWrongAnswerPenalty());
    $this->assertTrue($l->getIsShortAnswer());
    $this->assertEquals("A", $l->getAnswerChoice1());
    $this->assertEquals("B", $l->getAnswerChoice2());
    $this->assertEquals("C", $l->getAnswerChoice3());
    $this->assertEquals("D", $l->getAnswerChoice4());
  }

  public function testCreateFlag(): void {
    $id = HH\Asio\join(Level::genCreateFlag(
      'title 2',
      'description 2',
      'flag 2', // flag
      2, // entity_id
      3, // category_id
      15, // points
      2, // bonus
      1, // bonus_dec
      'hint 2', // hint
      2, // penalty
      5, //wrong_answer_penalty,
    ));

    $this->assertEquals(4, $id);
    $all = HH\Asio\join(Level::genAllLevels());
    $this->assertEquals(4, count($all));
    $l = $all[3];
    $this->assertEquals(4, $l->getId());
    $this->assertFalse($l->getActive());
    $this->assertEquals('flag', $l->getType());
    $this->assertEquals('title 2', $l->getTitle());
    $this->assertEquals('description 2', $l->getDescription());
    $this->assertEquals(2, $l->getEntityId());
    $this->assertEquals(3, $l->getCategoryId());
    $this->assertEquals(15, $l->getPoints());
    $this->assertEquals(2, $l->getBonus());
    $this->assertEquals(1, $l->getBonusDec());
    $this->assertEquals(2, $l->getBonusFix());
    $this->assertEquals('flag 2', $l->getFlag());
    $this->assertEquals('hint 2', $l->getHint());
    $this->assertEquals(2, $l->getPenalty());
    $this->assertEquals(5, $l->getWrongAnswerPenalty());
  }

  public function testUpdate(): void {
    HH\Asio\join(Level::genUpdateFlag(
      'title 2',
      'description 2',
      'flag 2', // flag
      2, // entity_id
      3, // category_id
      15, // points
      2, // bonus
      1, // bonus_dec
      'hint 2', // hint
      2, // penalty
      1, // level_id
      3, // wrong_answer_penalty
    ));

    $all = HH\Asio\join(Level::genAllLevels());
    $this->assertEquals(3, count($all));
    $l = $all[0];
    $this->assertEquals(1, $l->getId());
    $this->assertTrue($l->getActive());
    $this->assertEquals('base', $l->getType());
    $this->assertEquals('title 2', $l->getTitle());
    $this->assertEquals('description 2', $l->getDescription());
    $this->assertEquals(2, $l->getEntityId());
    $this->assertEquals(3, $l->getCategoryId());
    $this->assertEquals(15, $l->getPoints());
    $this->assertEquals(2, $l->getBonus());
    $this->assertEquals(1, $l->getBonusDec());
    $this->assertEquals(2, $l->getBonusFix());
    $this->assertEquals('flag 2', $l->getFlag());
    $this->assertEquals('hint 2', $l->getHint());
    $this->assertEquals(2, $l->getPenalty());
    $this->assertEquals(3, $l->getWrongAnswerPenalty());
  }

  public function testDelete(): void {
    HH\Asio\join(Level::genDelete(1));
    $all = HH\Asio\join(Level::genAllLevels());
    $this->assertEquals(2, count($all));
  }

  public function testSetStatus(): void {
    HH\Asio\join(Level::genSetStatus(2, false));
    $all = HH\Asio\join(Level::genAllLevels());
    $this->assertEquals(3, count($all));
    $l = $all[1];
    $this->assertFalse($l->getActive());
  }

  public function testSetStatusType(): void {
    HH\Asio\join(Level::genSetStatusType(false, 'base'));
    $all = HH\Asio\join(Level::genAllLevels());
    $this->assertEquals(3, count($all));
    $l = $all[0];
    $this->assertEquals('base', $l->getType());
    $this->assertFalse($l->getActive());
  }

  public function testSetStatusAll(): void {
    HH\Asio\join(Level::genSetStatusAll(false, 'base'));
    $all = HH\Asio\join(Level::genAllLevels());
    $this->assertEquals(3, count($all));
    $l = $all[0];
    $this->assertEquals('base', $l->getType());
    $this->assertFalse($l->getActive());
  }

  public function testAllActiveLevels(): void {
    $all = HH\Asio\join(Level::genAllActiveLevels());
    $this->assertEquals(3, count($all));
  }

  public function testAllActiveBases(): void {
    $all = HH\Asio\join(Level::genAllActiveBases());
    $this->assertEquals(1, count($all));
  }

  public function testAllTypeLevels(): void {
    $all = HH\Asio\join(Level::genAllTypeLevels('flag'));
    $this->assertEquals(1, count($all));
  }

  public function testAll(): void {
    $all = HH\Asio\join(Level::genAllQuizLevels());
    $this->assertEquals(1, count($all));
    $all = HH\Asio\join(Level::genAllBaseLevels());
    $this->assertEquals(1, count($all));
    $all = HH\Asio\join(Level::genAllFlagLevels());
    $this->assertEquals(1, count($all));
  }

  public function testCheckAnswer(): void {
    $this->assertFalse(HH\Asio\join(Level::genCheckAnswer(1, 'no')));
    $this->assertTrue(HH\Asio\join(Level::genCheckAnswer(1, 'flag')));
    $this->assertTrue(HH\Asio\join(Level::genCheckAnswer(1, 'FLAG')));
    $this->assertFalse(HH\Asio\join(Level::genCheckAnswer(2, 'no')));
    $this->assertTrue(HH\Asio\join(Level::genCheckAnswer(2, 'quiz')));
    $this->assertTrue(HH\Asio\join(Level::genCheckAnswer(2, 'QUIZ')));
    $this->assertFalse(HH\Asio\join(Level::genCheckAnswer(3, 'no')));
    $this->assertFalse(HH\Asio\join(Level::genCheckAnswer(3, 'FLAG')));
    $this->assertTrue(HH\Asio\join(Level::genCheckAnswer(3, 'flag')));
  }

  public function testAdjustBonus(): void {
    HH\Asio\join(Level::genAdjustBonus(1));
    $l = HH\Asio\join(Level::gen(1));
    $this->assertEquals(0, $l->getBonus());
  }
}
