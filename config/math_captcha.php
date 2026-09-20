<?php
// Simple Math CAPTCHA Configuration
class MathCaptcha {
    public static function generate() {
        $num1 = rand(1, 99);
        $num2 = rand(1, 9);
        $operator = rand(0, 1) ? '+' : '-';

        if ($operator === '-' && $num1 < $num2) {
            [$num1, $num2] = [$num2, $num1];
        }

        $answer = $operator === '+' ? $num1 + $num2 : $num1 - $num2;

        return [
            'num1'     => $num1,
            'operator' => $operator,
            'num2'     => $num2,
            'answer'   => $answer
        ];
    }

    public static function verify($userAnswer, $correctAnswer) {
        return is_numeric($userAnswer) && (int)$userAnswer === (int)$correctAnswer;
    }
}
?>