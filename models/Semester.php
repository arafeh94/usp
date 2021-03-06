<?php

namespace app\models;

use app\components\Tools;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "semester".
 *
 * @property int $SemesterId
 * @property int $Year
 * @property string $Season
 * @property string $StartDate
 * @property string $EndDate
 * @property bool $IsCurrent
 * @property int $CreatedByUserId
 * @property string $DateAdded
 * @property bool $IsDeleted
 *
 * @property EvaluationEmail[] $evaluationEmails
 * @property OfferedCourse[] $offeredCourse
 * @property StudentSemesterEnrollment[] $studentSemesterEnrollment
 */
class Semester extends ActiveRecord
{



    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'semester';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['Year', 'Season', 'StartDate', 'EndDate'], 'required'],
            [['Season'], 'string', 'min' => 0, 'max' => 8],
            [['Year', 'CreatedByUserId'], 'integer'],
            [['StartDate', 'EndDate', 'DateAdded'], 'safe'],
            [['IsCurrent', 'IsDeleted'], 'boolean'],
            ['EndDate', 'compare', 'compareAttribute' => 'StartDate', 'operator' => '>='],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'SemesterId' => Yii::t('app', 'Semester'),
            'Year' => Yii::t('app', 'Year'),
            'Season' => Yii::t('app', 'Season'),
            'StartDate' => Yii::t('app', 'Start Date'),
            'EndDate' => Yii::t('app', 'End Date'),
            'IsCurrent' => Yii::t('app', 'Is Current'),
            'CreatedByUserId' => Yii::t('app', 'Created By User ID'),
            'DateAdded' => Yii::t('app', 'Date Added'),
            'IsDeleted' => Yii::t('app', 'Is Deleted'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEvaluationEmails()
    {
        return $this->hasMany(EvaluationEmail::className(), ['SemesterId' => 'SemesterId']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOfferedCourses()
    {
        return $this->hasMany(Offeredcourse::className(), ['SemesterId' => 'SemesterId']);
    }


    /**
     * @return \yii\db\ActiveQuery
     */
    public function getStudentSemesterEnrollments()
    {
        return $this->hasMany(StudentSemesterEnrollment::className(), ['SemesterId' => 'SemesterId']);
    }

    /**
     * @inheritdoc
     * @return SemesterQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SemesterQuery(get_called_class());
    }
}
