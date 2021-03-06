<?php

namespace app\controllers;

use app\models\providers\SemesterDataProvider;
use app\models\search\SemesterSearchModel;
use app\models\Season;
use app\models\Semester;
use app\models\StudentSemesterEnrollment;
use app\models\User;
use yii\filters\AccessControl;
use yii\helpers\Json;

class TermController extends \yii\web\Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['admin'],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $provider = new SemesterDataProvider();
        $provider->search(\Yii::$app->request->get('SemesterSearchModel', []));
        return $this->render('index', ['provider' => $provider]);
    }

    public function actionView($id)
    {
        if (\Yii::$app->request->isAjax) {
            return Json::encode(Semester::find()->active()->filter()->id($id)->one());
        }
        return false;
    }

    public function actionUpdate()
    {
        if (\Yii::$app->request->isAjax) {
            $id = \Yii::$app->request->post('Semester')['SemesterId'];
            $model = $id === "" ? new Semester() : Semester::find()->active()->id($id)->one();
            if ($model->isNewRecord) $model->CreatedByUserId = User::get()->UserId;
            $saved = null;
            if ($model->load(\Yii::$app->request->post()) && $model->validate()) {
                $saved = $model->save();
                if ($saved && $model->IsCurrent) {
                    \Yii::$app->db->createCommand("update semester set IsCurrent = 0 WHERE SemesterId != $model->SemesterId")->execute();
                    Semester::find()->current(true);
                }
            }
            return $this->renderAjax('_form', ['model' => $model, 'saved' => $saved]);
        }
        return false;
    }

    public function actionDelete($id)
    {
        if (StudentSemesterEnrollment::find()->where(['SemesterId' => $id])->count()) {
            return false;
        }
        if (\Yii::$app->request->isAjax) {
            $model = Semester::findOne($id);
            $model->IsDeleted = 1;
            return $model->save();
        }
        return false;
    }

}
