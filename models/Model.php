<?php
namespace sersid\fileupload\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\helpers\FileHelper;

/**
 * This is the model class for table "{{%user_photo}}".
 *
 * @property integer $id
 * @property string $code
 * @property integer $is_image
 * @property integer $width
 * @property integer $height
 * @property integer $size
 * @property string $file_name
 * @property string $content_type
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $ext
 */
class Model extends \yii\db\ActiveRecord
{
    const STATUS_DELETED = 0;
    const STATUS_ACTIVE = 10;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%file}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['code', 'size', 'file_name', 'content_type', 'ext'], 'required'],
            [['size'], 'integer'],
            [['code', 'content_type'], 'string', 'max' => 32],
            [['file_name'], 'string', 'max' => 255],
            [['ext'], 'string', 'max' => 10],
            ['status', 'default', 'value' => self::STATUS_ACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_DELETED]],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete()
    {
        if(parent::beforeDelete()) {
            FileHelper::removeDirectory(Yii::$app->file->dir.'/'.$this->id.'/');
            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function afterFind()
    {
        parent::afterFind();
        $this->is_image == ($this->is_image !== null) ? boolval($this->is_image) : null;
    }
}