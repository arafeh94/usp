<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "studentcourseenrollment".
 *
 * @property int $StudentCourseEnrollmentId
 * @property int $StudentSemesterEnrollmentId
 * @property int $OfferedCourseId
 * @property string $FinalGrade
 * @property bool $IsDropped
 * @property bool $IsDeleted
 * @property int $CreatedByUserId
 * @property string $DateAdded
 * @property string $StudyPlanId
 *
 * @property Student $student
 * @property StudentSemesterEnrollment $studentSemesterEnrollment
 * @property OfferedCourse $offeredCourse
 */
class StudentCourseEnrollment extends \yii\db\ActiveRecord
{


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'studentcourseenrollment';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['StudentSemesterEnrollmentId', 'OfferedCourseId'], 'required'],
            [['StudentSemesterEnrollmentId', 'OfferedCourseId', 'CreatedByUserId', 'StudyPlanId'], 'integer'],
            [['OfferedCourseId'], 'unique', 'targetAttribute' => ['OfferedCourseId', 'StudentSemesterEnrollmentId'], 'filter' => ['IsDeleted' => 0, 'IsDropped' => 0], 'message' => 'Already enrolled in this semester'],
            [['IsDropped', 'IsDeleted'], 'boolean'],
            [['DateAdded'], 'safe'],
            [['FinalGrade'], 'double'],
            [['StudentSemesterEnrollmentId'], 'exist', 'skipOnError' => true, 'targetClass' => StudentSemesterEnrollment::className(), 'targetAttribute' => ['StudentSemesterEnrollmentId' => 'StudentSemesterEnrollmentId']],
            [['OfferedCourseId'], 'exist', 'skipOnError' => true, 'targetClass' => OfferedCourse::className(), 'targetAttribute' => ['OfferedCourseId' => 'OfferedCourseId']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'StudentCourseEnrollmentId' => Yii::t('app', 'Student Course Enrollment'),
            'StudentSemesterEnrollmentId' => Yii::t('app', 'Student Semester Enrollment'),
            'OfferedCourseId' => Yii::t('app', 'Offered Course'),
            'FinalGrade' => Yii::t('app', 'Final Grade'),
            'IsDropped' => Yii::t('app', 'Is Dropped'),
            'IsDeleted' => Yii::t('app', 'Is Deleted'),
            'CreatedByUserId' => Yii::t('app', 'Created By User ID'),
            'DateAdded' => Yii::t('app', 'Date Added'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getStudentSemesterEnrollment()
    {
        return $this->hasOne(StudentSemesterEnrollment::className(), ['StudentSemesterEnrollmentId' => 'StudentSemesterEnrollmentId']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getStudent()
    {
        return $this->hasOne(Student::className(), ['StudentId' => 'StudentSemesterEnrollmentId'])
            ->via('studentSemesterEnrollment')->inverseOf('studentCourseEnrollments');
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOfferedCourse()
    {
        return $this->hasOne(OfferedCourse::className(), ['OfferedCourseId' => 'OfferedCourseId']);
    }

    /**
     * @inheritdoc
     * @return StudentCourseEnrollmentQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new StudentCourseEnrollmentQuery(get_called_class());
    }
}
