<?php

namespace app\controllers;

use app\components\Shell;
use app\components\Tools;
use app\models\Course;
use app\models\Cycle;
use app\models\Department;
use app\models\EvaluationEmail;
use app\models\Instructor;
use app\models\InstructorEvaluationEmail;
use app\models\Major;
use app\models\OfferedCourse;
use app\models\providers\CourseDataProvider;
use app\models\providers\EvaluationReportDataProvider;
use app\models\providers\EvaluationValidateDataProvider;
use app\models\providers\MailingDataProvider;
use app\models\School;
use app\models\Semester;
use app\models\Student;
use app\models\StudentCourseEnrollment;
use app\models\StudentCourseEvaluation;
use app\models\StudentSemesterEnrollment;
use app\models\User;
use Symfony\Component\Finder\Glob;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\Controller;

class EvaluationController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'except' => ['fill'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $provider = new EvaluationReportDataProvider();
        $provider->search(\Yii::$app->request->get('EvaluationReportSearchModel', []));
        return $this->render('index', ['provider' => $provider]);
    }

    public function actionMailing()
    {
        $provider = new MailingDataProvider();
        $provider->search(\Yii::$app->request->get('MailingSearchModel', []));
        return $this->render('mailing', [
            'provider' => $provider,
            'semester' => Semester::find()->current()
        ]);
    }

    public function actionView($id)
    {
        if (\Yii::$app->request->isAjax) {
            return Json::encode(EvaluationEmail::find()->active()->filter()->id($id)->one());
        }
        return false;
    }

    public function actionUpdate()
    {
        if (\Yii::$app->request->isAjax) {

            $id = \Yii::$app->request->post('EvaluationEmail')['EvaluationEmailId'];
            $model = $id === "" ? new EvaluationEmail() : EvaluationEmail::find()->active()->id($id)->one();
            $isNewRecord = $model->isNewRecord;
            if ($isNewRecord) {
                $model->CreatedByUserId = User::get()->UserId;
                $model->Date = Tools::currentDate();
                $model->SemesterId = Semester::find()->current()->SemesterId;
            }
            $saved = null;

            if ($model->load(\Yii::$app->request->post()) && $model->validate()) {
                $saved = $model->save();
                if ($saved && $isNewRecord) $this->sendInstructorEmails($model);
            }
            return $this->renderAjax('_form', ['model' => $model, 'saved' => $saved, 'semester' => Semester::find()->current()]);
        }
        return false;
    }

    public function actionDelete($id)
    {
        if (\Yii::$app->request->isAjax) {
            $model = EvaluationEmail::findOne($id);
            $model->IsDeleted = 1;
            return $model->save();
        }
        return false;
    }

    public function actionSend($id)
    {
        $eval = InstructorEvaluationEmail::find()->active()->where(['InstructorEvaluationEmailId' => $id])->with('instructor')->one();
        $message = Yii::$app->mailer
            ->compose('evaluation/html', ['instructorEvaluationEmail' => $eval, 'instructor' => $eval->instructor])
            ->setFrom(Yii::$app->params['adminEmail'])
            ->setTo($eval->instructor->Email)
            ->setSubject('Evaluation Fill Request');
        $res = $message->send();
        if ($res !== true) Yii::error($res);
        return $res;
    }

    /**
     * @param EvaluationEmail $evaluationEmail
     */
    public function sendInstructorEmails($evaluationEmail)
    {
        $instructors = Instructor::find()
            ->innerJoin(OfferedCourse::tableName(), 'instructor.InstructorId=offeredcourse.InstructorId')
            ->innerJoin(StudentCourseEnrollment::tableName(), 'studentcourseenrollment.OfferedCourseId=offeredcourse.OfferedCourseId')
            ->innerJoin(StudentSemesterEnrollment::tableName(), 'studentcourseenrollment.StudentSemesterEnrollmentId=studentsemesterenrollment.StudentSemesterEnrollmentId')
            ->where(['studentcourseenrollment.IsDeleted' => 0])
            ->andWhere(['studentcourseenrollment.IsDropped' => 0])
            ->andWhere(['studentsemesterenrollment.IsDeleted' => 0])
            ->andWhere(['offeredcourse.IsDeleted' => 0])
            ->active()
            ->all();
        foreach ($instructors as $instructor) {
            $instEvalEmail = new InstructorEvaluationEmail();
            $instEvalEmail->EvaluationEmailId = $evaluationEmail->EvaluationEmailId;
            $instEvalEmail->InstructorId = $instructor->InstructorId;
            $instEvalEmail->EvaluationCode = Tools::random();
            $instEvalEmail->save();
        }

        Shell::yii('mailer/send-mails');
    }

    public function actionFill($code)
    {
        $evaluations = Yii::$app->request->post('StudentCourseEvaluation', []);
        $evaluationsModels = [];
        $instructorEvaluationEmail = InstructorEvaluationEmail::find()->where(['EvaluationCode' => $code])->active()->one();

        if (!$instructorEvaluationEmail) {
            return $this->render('error', ['name' => 'Evaluation Form Error', 'message' => 'WRONG EVALUATION CODE']);
        }
        if (!$instructorEvaluationEmail->evaluationEmail->AvailableForInstructors && Yii::$app->user->isGuest) {
            return $this->render('error', ['name' => 'Evaluation Form Error', 'message' => 'EVALUATION FORM NOT AVAILABLE']);
        }

        if ($instructorEvaluationEmail->DateFilled && Yii::$app->user->isGuest) {
            return $this->render('error', ['name' => 'Evaluation Form Error', 'message' => 'EVALUATION ALREADY FILLED']);
        }

        $enrollments = (new Query())
            ->select(['*', 'course.Name as CourseName', 'cycle.Name as CycleName'])
            ->from(StudentCourseEnrollment::tableName())
            ->innerJoin(OfferedCourse::tableName(), 'studentcourseenrollment.OfferedCourseId = offeredcourse.OfferedCourseId')
            ->innerJoin(Instructor::tableName(), 'offeredcourse.InstructorId = instructor.InstructorId')
            ->innerJoin(Course::tableName(), 'offeredcourse.CourseId = course.CourseId')
            ->innerJoin(StudentSemesterEnrollment::tableName(), 'studentcourseenrollment.StudentSemesterEnrollmentId = studentsemesterenrollment.StudentSemesterEnrollmentId')
            ->innerJoin(Student::tableName(), 'student.StudentId = studentsemesterenrollment.StudentId')
            ->innerJoin(Cycle::tableName(), 'student.CycleId = cycle.CycleId')
            ->orderBy('offeredcourse.CourseId')
            ->where(['studentsemesterenrollment.SemesterId' => $instructorEvaluationEmail->evaluationEmail->SemesterId])
            ->andWhere(['instructor.InstructorId' => $instructorEvaluationEmail->InstructorId])
            ->andWhere(['instructor.IsDeleted' => 0])
            ->andWhere(['offeredcourse.IsDeleted' => 0])
            ->andWhere(['studentsemesterenrollment.IsDeleted' => 0])
            ->andWhere(['studentcourseenrollment.IsDeleted' => 0])
            ->andWhere(['studentcourseenrollment.IsDropped' => 0])
            ->andWhere(['student.IsDeleted' => 0])
            ->all();
        if ($evaluations) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $hasError = false;
                foreach ($evaluations as $evaluation) {
                    $studentCourseEvaluation = new StudentCourseEvaluation();
                    if ($studentCourseEvaluation->load($evaluation, '')) {
                        if ($evaluation['StudentCourseEvaluationId']) {
                            //if it's an old record
                            $studentCourseEvaluation = StudentCourseEvaluation::findOne($evaluation['StudentCourseEvaluationId']);
                            $studentCourseEvaluation->load($evaluation, '');
                            $studentCourseEvaluation->save();
                        } else {
                            //if it's a new record
                            if (!$studentCourseEvaluation->save()) $hasError = true;
                        }
                        if (!$hasError && $instructorEvaluationEmail->evaluationEmail->Quarter == 'Final') {
                            $studentCourseEnrollment = StudentCourseEnrollment::findOne($evaluation['StudentCourseEnrollmentId']);
                            $studentCourseEnrollment->FinalGrade = (double)($studentCourseEvaluation->Grade);
                            if (!$studentCourseEnrollment->save(false)) $hasError = true;
                        }

                        if (!$hasError) {
                            $studentCourseEvaluation->studentCourseEnrollment->IsDropped = boolval($studentCourseEvaluation->Withdraw);
                            $studentCourseEvaluation->studentCourseEnrollment->save();
                        }

                        if (!$hasError && !$studentCourseEvaluation->studentCourseEnrollment->IsDropped && !$studentCourseEvaluation->studentCourseEnrollment->IsDeleted) {
                            $evaluationsModels[] = $studentCourseEvaluation;
                        }
                    }

                }
                if ($hasError) {
                    $transaction->rollBack();
                } else {
                    $instructorEvaluationEmail->DateFilled = Tools::currentDate();
                    $instructorEvaluationEmail->save();
                    $transaction->commit();
                }

            } catch (\Exception $e) {
                Tools::prettyPrint($e);
                $transaction->rollBack();
            }
        } else {
            //creating evaluation form
            if ($instructorEvaluationEmail->DateFilled && !Yii::$app->user->isGuest && !$evaluations) {
                $evaluationsModels = StudentCourseEvaluation::find()
                    ->innerJoinWith('studentCourseEnrollment')
                    ->innerJoinWith('studentCourseEnrollment.studentSemesterEnrollment')
                    ->where(['InstructorEvaluationEmailId' => $instructorEvaluationEmail->InstructorEvaluationEmailId])
                    ->andWhere(['studentcourseenrollment.IsDeleted' => 0])
                    ->andWhere(['studentcourseenrollment.IsDropped' => 0])
                    ->all();
            } else {
                foreach ($enrollments as $enrollment) {
                    $studentCourseEvaluation = new StudentCourseEvaluation();
                    $studentCourseEvaluation->InstructorEvaluationEmailId = $instructorEvaluationEmail->InstructorEvaluationEmailId;
                    $studentCourseEvaluation->StudentCourseEnrollmentId = $enrollment['StudentCourseEnrollmentId'];
                    $studentCourseEvaluation->StudentId = $enrollment['StudentId'];
                    $evaluationsModels[] = $studentCourseEvaluation;
                }

            }
        }

        return $this->render('fill', [
            'instructorEvaluationEmail' => $instructorEvaluationEmail,
            'enrollments' => $enrollments,
            'evaluations' => $evaluationsModels
        ]);
    }

    public function actionSetNote()
    {
        $request = Yii::$app->request;
        $hasEditable = $request->post('hasEditable', false);
        $StudentCourseEvaluationId = $request->post('StudentCourseEvaluationId', false);
        if ($hasEditable && $StudentCourseEvaluationId) {
            $studentCourseEvaluation = StudentCourseEvaluation::findOne($StudentCourseEvaluationId);
            if ($request->post('UserNote', false)) {
                $studentCourseEvaluation->UserNote = $request->post('UserNote');
            } else if ($request->post('AdminNote', false)) {
                $studentCourseEvaluation->AdminNote = $request->post('AdminNote');
            }
            $message = '';
            if (!$studentCourseEvaluation->save()) {
                $errors = $studentCourseEvaluation->getFirstErrors();
                $message = reset($errors);
            }
            echo Json::encode(['output' => 'OK', 'message' => $message]);
            Yii::$app->end(200);
        }
        return false;
    }


    public function actionValidate($evaluationId)
    {
        return $this->renderPartial('validate', ['provider' => new EvaluationValidateDataProvider(['evaluationEmailId' => $evaluationId])]);
    }

    public function actionMailTest()
    {
        $res = null;
        $request = Yii::$app->request;
        $to = $request->post('to', false);
        $content = $request->post('content', false);
        if ($to && $content) {
            $res = Yii::$app->mailer
                ->compose('test/html', ['content' => $content])
                ->setFrom(Yii::$app->params['adminEmail'])
                ->setTo($to)
                ->setSubject('USP Test Email')
                ->send();
        }
        return $this->render('mail-test', ['res' => $res]);
    }

}
