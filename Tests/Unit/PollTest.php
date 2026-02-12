<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZenithGram\ZenithGram\Poll;
use ZenithGram\ZenithGram\UpdateContext;
use ZenithGram\ZenithGram\Interfaces\ApiClientInterface;
use ZenithGram\ZenithGram\Enums\MessageParseMode;

class PollTest extends TestCase
{
    private $apiMock;
    private $contextMock;

    protected function setUp(): void
    {
        $this->apiMock = $this->createMock(ApiClientInterface::class);
        $this->contextMock = $this->createMock(UpdateContext::class);
    }

    /**
     * Тест создания обычного опроса (regular)
     */
    public function testCreateRegularPoll(): void
    {
        $this->contextMock->method('getChatId')->willReturn(123);

        $poll = new Poll('regular', $this->apiMock, $this->contextMock);

        $this->apiMock->expects($this->once())
            ->method('callAPI')
            ->with(
                'sendPoll',
                $this->callback(function($params) {
                    return $params['chat_id'] === 123 &&
                        $params['question'] === 'Your favorite color?' &&
                        $params['type'] === 'regular' &&
                        json_decode($params['options']) === ['Red', 'Blue'];
                })
            )
            ->willReturn(['ok' => true]);

        $poll->question('Your favorite color?')
            ->addAnswers('Red', 'Blue')
            ->send();
    }

    /**
     * Тест создания викторины (quiz) с правильным ответом и объяснением
     */
    public function testCreateQuizPoll(): void
    {
        $poll = new Poll('quiz', $this->apiMock, $this->contextMock);

        $this->apiMock->expects($this->once())
            ->method('callAPI')
            ->with(
                'sendPoll',
                $this->callback(function($params) {
                    return $params['type'] === 'quiz' &&
                        $params['correct_option_id'] === 1 &&
                        $params['explanation'] === 'Because 2+2=4' &&
                        $params['explanation_parse_mode'] === 'HTML';
                })
            );

        $poll->question('2 + 2 = ?')
            ->addAnswers('3', '4', '5')
            ->correctAnswer(1) // Индекс 1 (это '4')
            ->explanation('Because 2+2=4')
            ->explanationParseMode(MessageParseMode::HTML)
            ->send(999); // Явно передаем chat_id
    }

    /**
     * Тест анонимности и мульти-ответов
     */
    public function testPollSettings(): void
    {
        $poll = new Poll('regular', $this->apiMock, $this->contextMock);

        $this->apiMock->expects($this->once())
            ->method('callAPI')
            ->with(
                'sendPoll',
                $this->callback(function($params) {
                    return $params['is_anonymous'] === false &&
                        $params['allows_multiple_answers'] === true;
                })
            );

        $poll->isAnonymous(false)
            ->multipleAnswers(true)
            ->send(1);
    }

    /**
     * Тест валидации openPeriod (от 5 до 600 секунд)
     */
    public function testOpenPeriodValidation(): void
    {
        $poll = new Poll('quiz', $this->apiMock, $this->contextMock);

        $poll->openPeriod(2);

        $this->apiMock->expects($this->atLeastOnce())
            ->method('callAPI')
            ->with('sendPoll', $this->callback(fn($p) => $p['open_period'] === 600));

        $poll->send(1);
    }

    /**
     * Тест закрытия опроса
     */
    public function testClosePoll(): void
    {
        $poll = new Poll('regular', $this->apiMock, $this->contextMock);

        $this->apiMock->expects($this->once())
            ->method('callAPI')
            ->with('sendPoll', $this->callback(fn($p) => $p['is_closed'] === true));

        $poll->close(true)->send(1);
    }

    /**
     * Проверка, что correctAnswer и explanation НЕ передаются в regular опросе
     */
    public function testQuizFieldsNotPresentInRegularPoll(): void
    {
        $poll = new Poll('regular', $this->apiMock, $this->contextMock);

        $this->apiMock->expects($this->once())
            ->method('callAPI')
            ->with(
                'sendPoll',
                $this->callback(function($params) {
                    return !isset($params['correct_option_id']) &&
                        !isset($params['explanation']);
                })
            );

        $poll->correctAnswer(0)
            ->explanation('Should not be here')
            ->send(1);
    }
}
