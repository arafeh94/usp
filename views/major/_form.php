<?php
/**
 * Created by PhpStorm.
 * User: Arafeh
 * Date: 5/16/2018
 * Time: 3:38 PM
 */

/** @var $model Department */

/** @var $schools School[] */

/** @var $departments Department[] */

use app\models\Department;
use app\models\Major;
use app\models\School;
use kartik\widgets\Select2;
use yii\bootstrap\ActiveForm;
use yii\bootstrap\Alert;
use yii\bootstrap\Html;
use yii\helpers\ArrayHelper;


if (!isset($model)) $model = new Major();
?>


<?php if (isset($saved) && $saved): ?>
    <?= Alert::widget(['id' => 'form-state-alert', 'body' => 'saved', 'options' => ['class' => 'alert-info']]) ?>
    <script>if (window.gridControl) window.gridControl.shouldRefresh = true</script>
    <script>if (window.modalControl) window.modalControl.close()</script>
<?php endif; ?>

<?php $form = ActiveForm::begin([
    'id' => 'model-form',
    'action' => ['major/update'],
    'options' => ['data-pjax' => '']
]) ?>
<?= $form->field($model, 'MajorId')->hiddenInput()->label(false) ?>
<?php $departmentsSelect = ArrayHelper::map($departments, 'DepartmentId', function ($model) {
    return $model->school->Name . ' - ' . $model->Name;
}) ?>
<?= $form->field($model, 'DepartmentId')->widget(Select2::classname(), [
    'data' => $departmentsSelect,
    'options' => ['placeholder' => ''],
    'pluginOptions' => [
        'allowClear' => true
    ],
]); ?>
<?= $form->field($model, 'Name')->textInput() ?>
<?= $form->field($model, 'Abbreviation')->textInput() ?>
<?= $form->field($model, 'RequiredCredits')->textInput(['type' => 'number']) ?>
<div class="button-container">
    <?= Html::submitButton(Html::tag('i', '', ['class' => 'glyphicon glyphicon-refresh spin hidden']) . ' submit', ['class' => 'btn btn-success', 'id' => 'modal-form-submit']) ?>
    <?= Html::button('close', ['data-dismiss' => "modal", 'class' => 'btn btn-danger']) ?>
</div>
<?php ActiveForm::end(); ?>
