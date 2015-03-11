<?php
namespace sersid\fileupload;

use Yii;
use yii\base\Exception;
use yii\helpers\FileHelper;
use yii\web\HttpException;

/**
 * Class File
 * @package common\components
 */
class File extends \yii\base\Component
{
    /**
     * Directory
     * @var string
     */
    public $dir;

    /**
     * Model name
     * @var \sersid\fileupload\models\Model
     */
    public $model = 'sersid\fileupload\models\Model';

    /**
     * Large image size
     * @var int
     */
    public $largeSize = 1000;

    /**
     * Run component
     * @throws Exception
     */
    public function init()
    {
        $this->dir = Yii::getAlias($this->dir);
        if(!is_dir($this->dir)) {
            throw new Exception("$this->dir not directory");
        }

        if(!is_object($this->model)) {
            $this->model = new $this->model();
        }
    }

    /**
     * Upload file from URL
     * @param $url
     * @return bool
     */
    public function uploadFromUrl($url)
    {
        if(is_string($url)) {
            return $this->upload($url);
        }
        return false;
    }

    /**
     * Upload file
     * @param \yii\web\UploadedFile $file
     * @return bool
     */
    public function upload($file)
    {
        if(is_string($file)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $file);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $content = curl_exec($ch);
            $info = curl_getinfo($ch);
            if(curl_error($ch)) {
                return false;
            }
            curl_close($ch);

            if($info['http_code'] != 200) {
                return false;
            }

            $tempName = tempnam(sys_get_temp_dir(), 'php');
            $tempHandle = fopen($tempName, 'w');
            fwrite($tempHandle, $content);
            fclose($tempHandle);
            $arr = explode('/', $file);
            $fileName = explode('?', end($arr))[0];
            $this->model->size = filesize($tempName);
            $this->model->content_type = mime_content_type($tempName);
            $types = FileHelper::getExtensionsByMimeType($this->model->content_type);
            $this->model->ext = end($types);
            $this->model->file_name = $fileName ? $fileName : 'unnamed.'.$this->model->ext;
        } elseif($file instanceof \yii\web\UploadedFile) {
            $tempName = $file->tempName;
            $this->model->size = $file->size;
            $this->model->file_name = $file->name;
            $this->model->content_type = $file->type;
            $this->model->ext = $file->extension;
        } else {
            return false;
        }

        if(($imageInfo = getimagesize($tempName)) !== false) {
            $this->model->width = $imageInfo[0];
            $this->model->height = $imageInfo[1];
            $this->model->is_image = true;
        }
        $this->model->code = md5(time().implode('', $this->model->attributes));

        if(!$this->model->save()) {
            return false;
        }

        $dir = $this->dir.'/'.$this->model->id. '/';
        FileHelper::createDirectory($dir);

        $original =  $dir . 'original.' . $this->model->ext;
        if(is_string($file)) {
            @rename($tempName, $original);
        } else {
            if(!$file->saveAs($original)) {
                return false;
            }
        }

        if($fp = fopen($dir.$this->model->code.'.txt', 'w')) {
            fclose($fp);
        } else {
            return false;
        }
        return true;
    }

    /**
     * Render image
     * @param $id integer
     * @param $code string
     * @param $ext string
     * @param array $params
     * @throws HttpException
     */
    public function renderImage($id, $code, $ext, array $params = null)
    {
        set_time_limit(0);

        $dir = $this->dir.'/'.$id.'/';
        $file = $dir. 'original.' .$ext;
        if(!is_dir($dir) || !is_file($file) || !is_file($dir. $code . '.txt') || getimagesize($file) === false) {
            throw new HttpException(404, 'File not found');
        }

        $image = null;
        $fileLarge = $dir. 'large.' .$ext;
        if(is_file($fileLarge)) {
            $file = $fileLarge;
        } else {
            $image = Yii::$app->image->load($file);
            if($image->width > $this->largeSize || $image->height > $this->largeSize) {
                $image->resize($this->largeSize, $this->largeSize);
            }
            $image->save($fileLarge, 90);
        }

        if($params !== null) {
            $params = array_merge([
                'width' => 0,
                'height' => 0,
                'sharpen' => 0,
            ], $params);
            $fileSized = $dir.'w'.$params['width'].'_h'.$params['height'].'_sh'.$params['sharpen'].'.'.$ext;
            if(is_file($fileSized)) {
                $file = $fileSized;
            } else {
                if($image === null) {
                    $image = Yii::$app->image->load($file);
                }
                if($params['sharpen'] > 0) {
                    $image->sharpen($params['sharpen']);
                }
                if($params['width'] > 0 && $image->width > $params['width'] && $params['height'] > 0 && $image->height > $params['height']) {
                    $image->resize($params['width'], $params['height'], Yii\image\drivers\Image::PRECISE);
                    $image->crop($params['width'], $params['height']);
                } elseif ($params['width'] == 0 && $params['height'] > 0 && $image->height > $params['height']) {
                    $image->resize(null, $params['height']);
                } elseif ($params['width'] > 0 && $image->width > $params['width'] && $params['height'] == 0) {
                    $image->resize($params['width'], null);
                }
                $image->save($fileSized, 90);
            }
        }

        if($image === null) {
            $image = Yii::$app->image->load($file);
        }

        header("Content-Type: $image->mime");
        echo $image->render();
    }

    /**
     * Rotate photo
     * @param $id
     * @param $code string
     * @param $route integer
     * @return \yii\web\Response
     * @throws Exception
     * @throws HttpException
     */
    public function rotateImage($id, $code, $route)
    {
        $model = $this->model->findOne([
            'id' => $id,
        ]);
        if($model === null || $model->code != $code) {
            throw new HttpException(404, 'File not found');
        }

        $dir = $this->dir.'/'.$model->id.'/';
        if(!is_dir($dir)) {
            throw new Exception('Incorrect dir');
        }

        if (!($handle = opendir($dir))) {
            return false;
        }
        while (($file = readdir($handle)) !== false) {
            if(in_array($file, [
                '.',
                '..',
                'original.'.$model->ext,
                'large.'.$model->ext,
                $model->code.'.txt',
            ])) {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            unlink($path);
        }

        $fileLarge = $dir.'large.'.$model->ext;
        $file = $dir.'original.'.$model->ext;
        $file = is_file($fileLarge) ? $fileLarge : $file;
        $image = Yii::$app->image->load($file);
        return $image->rotate($route)->save();
    }
} 