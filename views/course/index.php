<?php
/* @var $this yii\web\View */

use app\components\ModalForm;
use app\models\Major;

echo ModalForm::widget([
    'formPath' => '@app/views/course/_form',
    'title' => 'Course',
    'formParams' => [
        'majors' => Major::find()->active()->all(),
    ]
]);

?>

<?= \app\components\GridViewBuilder::render($provider, 'Course') ?>
