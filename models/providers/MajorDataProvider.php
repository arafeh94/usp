<?php
/**
 * Created by PhpStorm.
 * User: Arafeh
 * Date: 5/16/2018
 * Time: 2:57 PM
 */

namespace app\models\providers;


use app\components\GridConfig;
use app\models\Department;
use app\models\Major;
use app\models\search\DepartmentSearchModel;
use app\models\search\MajorSearchModel;
use kartik\grid\DataColumn;
use kartik\grid\GridView;
use yii\bootstrap\Html;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

class MajorDataProvider extends ActiveDataProvider implements GridConfig
{
    public $searchModel;

    public function init()
    {
        parent::init();
        $this->query = Major::find()
            ->innerJoinWith('department', true)
            ->innerJoinWith('department.school', true)
            ->orderBy('major.Name')
            ->active();
        $this->sort->attributes['department'] = [
            'asc' => ['department.Name' => SORT_ASC],
            'desc' => ['department.Name' => SORT_DESC],
        ];
    }

    /**
     * @return array
     */
    public function gridColumns()
    {
        return [
            [
                'label' => 'Major',
                'class' => DataColumn::className(),
                'attribute' => 'Name',
            ],
            [
                'class' => DataColumn::className(),
                'attribute' => 'department',
                'label' => 'Department',
                'value' => function ($model) {
                    return $model->department->school->Name . ' - ' . $model->department->Name;
                },
                'filterType' => GridView::FILTER_SELECT2,
                'filter' => ArrayHelper::map(Department::find()->orderBy('Name')->active()->all(), 'Name', function ($model) {
                    return $model->school->Name . ' - ' . $model->Name;
                }),
                'filterWidgetOptions' => [
                    'pluginOptions' => ['allowClear' => true],
                ],
                'filterInputOptions' => ['placeholder' => ''],
            ],
            [
                'label' => 'Required Credits',
                'class' => DataColumn::className(),
                'attribute' => 'RequiredCredits',
                'filterInputOptions' => ['placeholder' => 'bellow or equal', 'class' => 'form-control'],
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{study-plan} {update} {delete}',
                'buttons' => [
                    'study-plan' => function ($key, $model, $index) {
                        $url = Url::to(['study-plan/index', 'major' => $model->getPrimaryKey()]);
                        return Html::tag('span', '', [
                            'class' => "glyphicon glyphicon-book pointer",
                            'onclick' => "location.href='$url'",
                        ]);
                    },
                    'update' => function ($key, $model, $index) {
                        $url = Url::to(['major/view', 'id' => $model->getPrimaryKey()]);
                        return Html::tag('span', '', [
                            'class' => "glyphicon glyphicon-pencil pointer",
                            'onclick' => "modalForm(this,'$url')",
                        ]);
                    },
                    'delete' => function ($key, $model, $index) {
                        $url = Url::to(['major/delete', 'id' => $model->MajorId]);
                        return Html::tag('span', '', [
                            'class' => "glyphicon glyphicon-trash pointer",
                            'onclick' => "gridControl.delete(this,'$url')",
                        ]);
                    },
                ]
            ]
        ];
    }

    public function search($params)
    {
        $this->searchModel($params);
        $this->query->andFilterWhere(['like', 'lower(major.Name)', strtolower(ArrayHelper::getValue($params, 'Name', ''))]);
        $this->query->andFilterWhere(['like', "concat(school.Name,' - ',department.Name)", ArrayHelper::getValue($params, 'department', '')]);
        if (($credits = ArrayHelper::getValue($params, 'RequiredCredits', null)) !== null) {
            $this->query->andFilterWhere(['<=', "major.RequiredCredits", $credits]);
        }
    }

    public function searchModel($params = null)
    {
        if ($this->searchModel === null) {
            $this->searchModel = new MajorSearchModel();
        }

        if ($params) {
            $this->searchModel->load($params, '');
        }

        return $this->searchModel;

    }
}