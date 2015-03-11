<?php
use yii\db\Schema;
use yii\db\Migration;

class m141205_112111_fileupload extends Migration
{
    /**
     * Table name
     * @var string
     */
    public $table = '{{%file}}';

    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable($this->table, [
            'id' => Schema::TYPE_PK,
            'code' => Schema::TYPE_STRING . '(32) NOT NULL',
            'is_image' => Schema::TYPE_BOOLEAN,
            'width' => Schema::TYPE_INTEGER . '(32) NOT NULL',
            'height' => Schema::TYPE_INTEGER . '(32) NOT NULL',
            'size' => Schema::TYPE_INTEGER . '(32) NOT NULL',
            'file_name' => Schema::TYPE_STRING . ' NOT NULL',
            'content_type' => Schema::TYPE_STRING . '(32) NOT NULL',
            'ext' => Schema::TYPE_STRING . '(10) NOT NULL',
            'status' => Schema::TYPE_SMALLINT . ' NOT NULL DEFAULT 10',
            'created_at' => Schema::TYPE_INTEGER . ' NOT NULL',
            'updated_at' => Schema::TYPE_INTEGER . ' NOT NULL',
        ], $tableOptions);
    }

    public function down()
    {
        $this->dropTable($this->table);
    }
}
