<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEventosInscripciones extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 80],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 200],
            'programa_json' => ['type' => 'LONGTEXT'],
            'creado_en' => ['type' => 'TIMESTAMP', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('eventos', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'evento_id' => ['type' => 'INT', 'unsigned' => true],
            'documento' => ['type' => 'VARCHAR', 'constraint' => 40],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 200],
            'correo' => ['type' => 'VARCHAR', 'constraint' => 200],
            'total' => ['type' => 'INT', 'unsigned' => true],
            'estado' => ['type' => 'VARCHAR', 'constraint' => 30],
            'cotizacion_json' => ['type' => 'LONGTEXT'],
            'entrada_json' => ['type' => 'LONGTEXT'],
            'notas_json' => ['type' => 'LONGTEXT'],
            'comprobante' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'documento_activo' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'creado_en' => ['type' => 'TIMESTAMP', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['evento_id', 'documento_activo']);
        $this->forge->addForeignKey('evento_id', 'eventos', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('inscripciones', true);
    }

    public function down()
    {
        $this->forge->dropTable('inscripciones', true);
        $this->forge->dropTable('eventos', true);
    }
}
