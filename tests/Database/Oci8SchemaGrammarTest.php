<?php

namespace Yajra\Oci8\Tests\Database;

use Illuminate\Database\Query\Expression;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Yajra\Oci8\Oci8Connection as Connection;
use Yajra\Oci8\Schema\Grammars\OracleGrammar;
use Yajra\Oci8\Schema\OracleBlueprint as Blueprint;
use Yajra\Oci8\Schema\OracleBuilder;

class Oci8SchemaGrammarTest extends TestCase
{
    public function tearDown(): void
    {
        m::close();
    }

    public function testBasicCreateTable()
    {
        $conn = $this->getConnection();

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->increments('id');
        $blueprint->string('email');
        $blueprint->create();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'create table "USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, constraint users_id_pk primary key ( "ID" ) )',
            $statements[0]
        );
    }

    protected function getConnection(
        ?OracleGrammar $grammar = null,
        ?OracleBuilder $builder = null,
        string $prefix = '',
        int $maxLength = 30
    ) {
        $connection = m::mock(Connection::class)
            ->shouldReceive('getMaxLength')->andReturn($maxLength)
            ->shouldReceive('getTablePrefix')->andReturn($prefix)
            ->shouldReceive('getConfig')->with('prefix_indexes')->andReturn(null)
            ->shouldReceive('isMaria')->andReturn(false)
            ->getMock();

        $grammar ??= $this->getGrammar($connection);
        $builder ??= $this->getBuilder();

        return $connection
            ->shouldReceive('getSchemaGrammar')->andReturn($grammar)
            ->shouldReceive('getSchemaBuilder')->andReturn($builder)
            ->getMock();
    }

    public function getGrammar(?Connection $connection = null)
    {
        return new OracleGrammar($connection ?? $this->getConnection());
    }

    public function getBuilder()
    {
        return mock(OracleBuilder::class);
    }

    public function testAddColumnWithSpace(): void
    {
        $conn = $this->getConnection();

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->create();
        $blueprint->string('first name');


        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create table "USERS" ( "FIRST NAME" varchar2(255) not null )', $statements[0]);
    }

    public function testCreateIndexNameUsingColumnWithSpace()
    {
        $conn = $this->getConnection();

        $blueprint = new Blueprint($conn,'users');
        $blueprint->create();
        $blueprint->string('first name');
        $blueprint->index('first name');

        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertEquals('create table "USERS" ( "FIRST NAME" varchar2(255) not null )', $statements[0]);
        $this->assertEquals('create index users_first_name_index on "USERS" ( "FIRST NAME" )', $statements[1]);
    }

    public function testBasicCreateTableWithReservedWords()
    {
        $conn = $this->getConnection();

        $blueprint = new Blueprint($conn,'users');
        $blueprint->create();
        $blueprint->increments('id');
        $blueprint->string('group');

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create table "USERS" ( "ID" number(10,0) not null, "GROUP" varchar2(255) not null, constraint users_id_pk primary key ( "ID" ) )',
            $statements[0]);
    }

    public function testBasicCreateTableWithPrimary()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create table "USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, constraint users_id_pk primary key ( "ID" ) )',
            $statements[0]);
    }

    public function testBasicCreateTableWithPrimaryAndForeignKeys()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn,'users');
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $blueprint->integer('foo_id');
        $blueprint->foreign('foo_id')->references('id')->on('orders');
        $blueprint->create();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, "FOO_ID" number(10,0) not null, constraint prefix_users_foo_id_fk foreign key ( "FOO_ID" ) references "PREFIX_ORDERS" ( "ID" ), constraint prefix_users_id_pk primary key ( "ID" ) )',
            $statements[0]
        );
    }

    public function testBasicCreateTableWithNvarchar2()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->create();
        $blueprint->increments('id');
        $blueprint->nvarchar2('first_name');

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'create table "USERS" ( "ID" number(10,0) not null, "FIRST_NAME" nvarchar2(255) not null, constraint users_id_pk primary key ( "ID" ) )',
            $statements[0]
        );
    }

    public function testBasicCreateTableWithDefaultValueAndIsNotNull()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email')->default('user@test.com');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create table "USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) default \'user@test.com\' not null, constraint users_id_pk primary key ( "ID" ) )',
            $statements[0]);
    }

    public function testBasicCreateTableWithPrefix()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $conn->shouldReceive('getConfig')->andReturn(null);

        $blueprint = new Blueprint($conn, 'users');
        $blueprint->create();
        $blueprint->increments('id');
        $blueprint->string('email');

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, constraint prefix_users_id_pk primary key ( "ID" ) )',
            $statements[0]);
    }

    public function testBasicCreateTableWithPrefixAndPrimary()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn,'users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $grammar = $this->getGrammar();

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, constraint prefix_users_id_pk primary key ( "ID" ) )',
            $statements[0]);
    }

    public function testBasicCreateTableWithPrefixPrimaryAndForeignKeys()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn,'users');
        $blueprint->create();
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $blueprint->integer('foo_id');
        $blueprint->foreign('foo_id')->references('id')->on('orders');
        $grammar = $this->getGrammar();

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, "FOO_ID" number(10,0) not null, constraint prefix_users_foo_id_fk foreign key ( "FOO_ID" ) references "PREFIX_ORDERS" ( "ID" ), constraint prefix_users_id_pk primary key ( "ID" ) )',
            $statements[0]
        );
    }

    public function testBasicCreateTableWithPrefixPrimaryAndForeignKeysWithCascadeDelete()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn,'users');
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $blueprint->integer('foo_id');
        $blueprint->foreign('foo_id')->references('id')->on('orders')->onDelete('cascade');
        $blueprint->create();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, "FOO_ID" number(10,0) not null, constraint prefix_users_foo_id_fk foreign key ( "FOO_ID" ) references "PREFIX_ORDERS" ( "ID" ) on delete cascade, constraint prefix_users_id_pk primary key ( "ID" ) )',
            $statements[0]
        );
    }

    public function testBasicAlterTable()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->increments('id');
        $blueprint->string('email');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertSame([
            'alter table "USERS" add ( "ID" number(10,0) not null )',
            'alter table "USERS" add ( "EMAIL" varchar2(255) not null )',
        ], $statements);
    }

    public function testAlterTableRenameColumn()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->renameColumn('email', 'email_address');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" rename column "EMAIL" to "EMAIL_ADDRESS"', $statements[0]);
    }

    public function testAlterTableRenameMultipleColumns()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->renameColumn('email', 'email_address');
        $blueprint->renameColumn('address', 'address_1');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertEquals('alter table "USERS" rename column "EMAIL" to "EMAIL_ADDRESS"', $statements[0]);
        $this->assertEquals('alter table "USERS" rename column "ADDRESS" to "ADDRESS_1"', $statements[1]);
    }

    public function testAlterTableModifyColumn()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->string('email')->change();

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" modify "EMAIL" varchar2(255) not null', $statements[0]);
    }

    public function testAlterTableModifyColumnWithCollate()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->string('email')->change()->collation('latin1_swedish_ci');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame([
            'alter table "USERS" modify "EMAIL" varchar2(255) collate "LATIN1_SWEDISH_CI" not null',
        ], $statements);
    }

    public function testAlterTableModifyMultipleColumns()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->string('email')->change();
        $blueprint->string('name')->change();

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertSame([
            'alter table "USERS" modify "EMAIL" varchar2(255) not null',
            'alter table "USERS" modify "NAME" varchar2(255) not null',
        ], $statements);
    }

    public function testBasicAlterTableWithPrimary()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->increments('id')->primary();
        $blueprint->string('email');

        $conn = $this->getConnection();

        $statements = $blueprint->toSql();

        $this->assertCount(3, $statements);
        $this->assertSame([
            'alter table "USERS" add ( "ID" number(10,0) not null )',
            'alter table "USERS" add ( "EMAIL" varchar2(255) not null )',
            'alter table "USERS" add constraint users_id_pk primary key ("ID")',
        ], $statements);
    }

    public function testBasicAlterTableWithPrefix()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn,'users');
        $blueprint->increments('id');
        $blueprint->string('email');

        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertSame([
            'alter table "PREFIX_USERS" add ( "ID" number(10,0) not null )',
            'alter table "PREFIX_USERS" add ( "EMAIL" varchar2(255) not null )',
        ], $statements);
    }

    public function testBasicAlterTableWithPrefixAndPrimary()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn,'users');
        $blueprint->increments('id')->primary();
        $blueprint->string('email');

        $statements = $blueprint->toSql();

        $this->assertCount(3, $statements);
        $this->assertEquals([
            'alter table "PREFIX_USERS" add ( "ID" number(10,0) not null )',
            'alter table "PREFIX_USERS" add ( "EMAIL" varchar2(255) not null )',
            'alter table "PREFIX_USERS" add constraint prefix_users_id_pk primary key ("ID")',
        ], $statements);
    }

    public function testDropTable()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->drop();
        $statements = $blueprint->toSql();

        $this->assertEquals(1, count($statements));
        $this->assertEquals('drop table "USERS"', $statements[0]);
    }

    public function testCompileTableExistsMethod()
    {
        $grammar = $this->getGrammar();
        $expected = "select count(*) from all_tables where upper(owner) = upper('schema') and upper(table_name) = upper('test_table')";
        $sql = $grammar->compileTableExists('schema', 'test_table');
        $this->assertEquals($expected, $sql);
    }

    public function testCompileColumnExistsMethod()
    {
        $grammar = $this->getGrammar();
        $expected = 'select column_name from all_tab_cols where upper(owner) = upper(\'schema\') and upper(table_name) = upper(\'test_table\') order by column_id';
        $sql = $grammar->compileColumnExists('schema', 'test_table');
        $this->assertEquals($expected, $sql);
    }

    public function testCompileColumnsMethod()
    {
        $grammar = $this->getGrammar();
        $expected = '
            select
                t.column_name as name,
                nvl(t.data_type_mod, data_type) as type_name,
                null as auto_increment,
                t.data_type as type,
                t.data_length,
                t.char_length,
                t.data_precision as precision,
                t.data_scale as places,
                decode(t.nullable, \'Y\', 1, 0) as nullable,
                t.data_default as "default",
                c.comments as "comment"
            from all_tab_cols t
            left join all_col_comments c on t.owner = c.owner and t.table_name = c.table_name AND t.column_name = c.column_name
            where upper(t.table_name) = upper(\'test_table\')
                and upper(t.owner) = upper(\'schema\')
            order by
                t.column_id
        ';

        $sql = $grammar->compileColumns('schema', 'test_table');
        $this->assertEquals($expected, $sql);
    }

    public function testCompileForeignKeysMethod()
    {
        $grammar = $this->getGrammar();
        $expected = '
            select
                kc.constraint_name as name,
                LISTAGG(kc.column_name, \',\') WITHIN GROUP (ORDER BY kc.position) as columns,
                rc.r_owner as foreign_schema,
                kcr.table_name as foreign_table,
                LISTAGG(kcr.column_name, \',\') WITHIN GROUP (ORDER BY kcr.position) as foreign_columns,
                rc.delete_rule AS "on_delete",
                null AS "on_update"
            from all_cons_columns kc
            inner join all_constraints rc ON kc.constraint_name = rc.constraint_name
            inner join all_cons_columns kcr ON kcr.constraint_name = rc.r_constraint_name
            where kc.table_name = upper(\'test_table\')
                and rc.r_owner = upper(\'schema\')
                and rc.constraint_type = \'R\'
            group by
                kc.constraint_name, rc.r_owner, kcr.table_name, kc.constraint_name, rc.delete_rule
        ';

        $sql = $grammar->compileForeignKeys('schema', 'test_table');
        $this->assertEquals($expected, $sql);
    }

    public function testDropTableIfExists()
    {
        $conn = $this->getConnection();
        $conn->shouldReceive('getConfig')->with('username')->andReturn('system');

        $blueprint = new Blueprint($conn,'users');
        $blueprint->dropIfExists();

        $statements = $blueprint->toSql();

        $this->assertEquals(1, count($statements));
        $this->assertEquals("declare c int;
            begin
               select count(*) into c from user_tables
               where upper(table_name) = upper('USERS') and upper(tablespace_name) = upper('system');
               if c = 1 then
                  execute immediate 'drop table \"USERS\"';
               end if;
            end;", $statements[0]);
    }

    public function testDropTableWithPrefix()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn,'users');
        $blueprint->drop();

        $grammar = $this->getGrammar();

        $statements = $blueprint->toSql($this->getConnection(), $grammar);

        $this->assertEquals(1, count($statements));
        $this->assertEquals('drop table "PREFIX_USERS"', $statements[0]);
    }

    public function testDropColumn()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->dropColumn('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" drop ( "FOO" )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->dropColumn(['foo', 'bar']);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" drop ( "FOO", "BAR" )', $statements[0]);
    }

    public function testDropPrimary()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->dropPrimary('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" drop constraint foo', $statements[0]);
    }

    public function testDropUnique()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->dropUnique('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" drop constraint foo', $statements[0]);
    }

    public function testDropIndex()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->dropIndex('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('drop index foo', $statements[0]);
    }

    public function testDropForeign()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->dropForeign('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" drop constraint foo', $statements[0]);
    }

    public function testDropTimestamps()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->dropTimestamps();
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" drop ( "CREATED_AT", "UPDATED_AT" )', $statements[0]);
    }

    public function testRenameTable()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->rename('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" rename to "FOO"', $statements[0]);
    }

    public function testRenameTableWithPrefix()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn,'users');
        $blueprint->rename('foo');
        $grammar = $this->getGrammar();
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "PREFIX_USERS" rename to "PREFIX_FOO"', $statements[0]);
    }

    public function testAddingPrimaryKey()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->primary('foo', 'bar');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add constraint bar primary key ("FOO")', $statements[0]);
    }

    public function testAddingPrimaryKeyWithConstraintAutomaticName()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->primary('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add constraint users_foo_pk primary key ("FOO")', $statements[0]);
    }

    public function testAddingPrimaryKeyWithConstraintAutomaticNameGreaterThanThirtyCharacters()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->primary('reset_password_secret_code');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals(
            'alter table "USERS" add constraint user_rese_passwor_secre_cod_pk primary key ("RESET_PASSWORD_SECRET_CODE")',
            $statements[0]);
    }

    public function testAddingUniqueKey()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->unique('foo', 'bar');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add constraint bar unique ( "FOO" )', $statements[0]);
    }

    public function testAddingDefinedUniqueKeyWithPrefix()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn,'users');
        $blueprint->unique('foo', 'bar');

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "PREFIX_USERS" add constraint bar unique ( "FOO" )', $statements[0]);
    }

    public function testAddingGeneratedUniqueKeyWithPrefix()
    {
        $conn = $this->getConnection(prefix: 'prefix_');
        $blueprint = new Blueprint($conn,'users');
        $blueprint->unique('foo');

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "PREFIX_USERS" add constraint prefix_users_foo_uk unique ( "FOO" )',
            $statements[0]);
    }

    public function testAddingIndex()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->index(['foo', 'bar'], 'baz');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('create index baz on "USERS" ( "FOO", "BAR" )', $statements[0]);
    }

    public function testAddingForeignKey()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->foreign('foo_id')->references('id')->on('orders');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add constraint users_foo_id_fk foreign key ( "FOO_ID" ) references "ORDERS" ( "ID" )',
            $statements[0]);
    }

    public function testAddingForeignKeyWithCascadeDelete()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->foreign('foo_id')->references('id')->on('orders')->onDelete('cascade');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add constraint users_foo_id_fk foreign key ( "FOO_ID" ) references "ORDERS" ( "ID" ) on delete cascade',
            $statements[0]);
    }

    public function testAddingIncrementingID()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->increments('id');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "ID" number(10,0) not null )', $statements[0]);
    }

    public function testAddingString()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->string('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" varchar2(255) not null )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->string('foo', 100);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" varchar2(100) not null )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->string('foo', 100)->nullable()->default('bar');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" varchar2(100) default \'bar\' null )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->string('foo', 100)
            ->nullable()
            ->default(new Expression('CURRENT TIMESTAMP'));
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" varchar2(100) default CURRENT TIMESTAMP null )',
            $statements[0]);
    }

    public function testAddingLongText()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->longText('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" clob not null )', $statements[0]);
    }

    public function testAddingMediumText()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->mediumText('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" clob not null )', $statements[0]);
    }

    public function testAddingText()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->text('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" clob not null )', $statements[0]);
    }

    public function testAddingChar()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->char('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" char(255) not null )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->char('foo', 1);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" char(1) not null )', $statements[0]);
    }

    public function testAddingBigInteger()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->bigInteger('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" number(19,0) not null )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->bigInteger('foo', true);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame([
            'alter table "USERS" add ( "FOO" number(19,0) not null )',
        ], $statements);
    }

    public function testAddingInteger()
    {
        $conn = $this->getConnection();

        $blueprint = new Blueprint($conn,'users');
        $blueprint->integer('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame(['alter table "USERS" add ( "FOO" number(10,0) not null )'], $statements);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->integer('foo', true)->primary();
        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertSame([
            'alter table "USERS" add ( "FOO" number(10,0) not null )',
            'alter table "USERS" add constraint users_foo_pk primary key ("FOO")',
        ], $statements);
    }

    public function testAddingMediumInteger()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->mediumInteger('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame(['alter table "USERS" add ( "FOO" number(7,0) not null )'], $statements);
    }

    public function testAddingSmallInteger()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->smallInteger('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" number(5,0) not null )', $statements[0]);
    }

    public function testAddingTinyInteger()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->tinyInteger('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" number(3,0) not null )', $statements[0]);
    }

    public function testAddingFloat()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->float('foo', 5);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" float(5) not null )', $statements[0]);

        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->float('foo');
        $statements = $blueprint->toSql();
        $this->assertEquals('alter table "USERS" add ( "FOO" float(126) not null )', $statements[0]);
    }

    public function testAddingDouble()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->double('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" float(126) not null )', $statements[0]);
    }

    public function testAddingDecimal()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->decimal('foo', 5, 2);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" number(5, 2) not null )', $statements[0]);
    }

    public function testAddingBoolean()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->boolean('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" char(1) not null )', $statements[0]);
    }

    public function testAddingEnum()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->enum('foo', ['bar', 'baz']);
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" varchar2(255) not null check ("FOO" in (\'bar\', \'baz\')) )',
            $statements[0]);
    }

    public function testAddingEnumWithDefaultValue()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->enum('foo', ['bar', 'baz'])->default('bar');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" varchar2(255) default \'bar\' not null check ("FOO" in (\'bar\', \'baz\')) )',
            $statements[0]);
    }

    public function testAddingJson()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->json('foo');
        $statements = $blueprint->toSql();
        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" clob not null )', $statements[0]);
    }

    public function testAddingJsonb()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->jsonb('foo');
        $statements = $blueprint->toSql();
        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" clob not null )', $statements[0]);
    }

    public function testAddingDate()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->date('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" date not null )', $statements[0]);
    }

    public function testAddingDateTime()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->dateTime('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" date not null )', $statements[0]);
    }

    public function testAddingTime()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->time('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" date not null )', $statements[0]);
    }

    public function testAddingTimeStamp()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->timestamp('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" timestamp not null )', $statements[0]);
    }

    public function testAddingTimeStampTz()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->timestampTz('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" timestamp with time zone not null )', $statements[0]);
    }

    public function testAddingNullableTimeStamps()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->nullableTimestamps();
        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertSame([
            'alter table "USERS" add ( "CREATED_AT" timestamp null )',
            'alter table "USERS" add ( "UPDATED_AT" timestamp null )',
        ], $statements);
    }

    public function testAddingTimeStamps()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->timestamps();
        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertSame([
            'alter table "USERS" add ( "CREATED_AT" timestamp null )',
            'alter table "USERS" add ( "UPDATED_AT" timestamp null )',
        ], $statements);
    }

    public function testAddingTimeStampTzs()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->timestampsTz();
        $statements = $blueprint->toSql();

        $this->assertCount(2, $statements);
        $this->assertEquals([
            'alter table "USERS" add ( "CREATED_AT" timestamp with time zone null )',
            'alter table "USERS" add ( "UPDATED_AT" timestamp with time zone null )',
        ], $statements);
    }

    public function testAddingUuid()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->uuid('foo');
        $statements = $blueprint->toSql();
        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" char(36) not null )', $statements[0]);
    }

    public function testAddingBinary()
    {
        $conn = $this->getConnection();
        $blueprint = new Blueprint($conn,'users');
        $blueprint->binary('foo');
        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertEquals('alter table "USERS" add ( "FOO" blob not null )', $statements[0]);
    }

    public function testBasicCreateTableWithPrimaryAndLongForeignKeys()
    {
        $conn = $this->getConnection(prefix: 'prefix_', maxLength: 120);
        $conn->shouldReceive('setMaxLength');
        $conn->setMaxLength(120);

        $blueprint = new Blueprint($conn,'users');
        $blueprint->integer('id')->primary();
        $blueprint->string('email');
        $blueprint->integer('very_long_foo_bar_id');
        $blueprint->foreign('very_long_foo_bar_id')->references('id')->on('orders');
        $blueprint->create();

        $statements = $blueprint->toSql();

        $this->assertCount(1, $statements);
        $this->assertSame([
            'create table "PREFIX_USERS" ( "ID" number(10,0) not null, "EMAIL" varchar2(255) not null, "VERY_LONG_FOO_BAR_ID" number(10,0) not null, constraint prefix_users_very_long_foo_bar_id_fk foreign key ( "VERY_LONG_FOO_BAR_ID" ) references "PREFIX_ORDERS" ( "ID" ), constraint prefix_users_id_pk primary key ( "ID" ) )',
        ], $statements);
    }

    public function testDropAllTables()
    {
        $statement = $this->getGrammar()->compileDropAllTables();

        $expected = 'BEGIN
            FOR c IN (SELECT table_name FROM user_tables WHERE secondary = \'N\') LOOP
            EXECUTE IMMEDIATE (\'DROP TABLE "\' || c.table_name || \'" CASCADE CONSTRAINTS\');
            END LOOP;

            FOR s IN (SELECT sequence_name FROM user_sequences) LOOP
            EXECUTE IMMEDIATE (\'DROP SEQUENCE \' || s.sequence_name);
            END LOOP;

            END;';

        $this->assertEquals($expected, $statement);
    }

    public function testCompileEnableForeignKeyConstraints()
    {
        $statement = $this->getGrammar()->compileEnableForeignKeyConstraints('username');

        $expected = 'begin
            for s in (
                SELECT \'alter table \' || c2.table_name || \' enable constraint \' || c2.constraint_name as statement
                FROM all_constraints c
                         INNER JOIN all_constraints c2
                                    ON (c.constraint_name = c2.r_constraint_name AND c.owner = c2.owner)
                         INNER JOIN all_cons_columns col
                                    ON (c.constraint_name = col.constraint_name AND c.owner = col.owner)
                WHERE c2.constraint_type = \'R\'
                  AND c.owner = \'USERNAME\'
                )
                loop
                    execute immediate s.statement;
                end loop;
        end;';

        $this->assertEquals($expected, $statement);
    }

    public function testCompileDisableForeignKeyConstraints()
    {
        $statement = $this->getGrammar()->compileDisableForeignKeyConstraints('username');

        $expected = 'begin
            for s in (
                SELECT \'alter table \' || c2.table_name || \' disable constraint \' || c2.constraint_name as statement
                FROM all_constraints c
                         INNER JOIN all_constraints c2
                                    ON (c.constraint_name = c2.r_constraint_name AND c.owner = c2.owner)
                         INNER JOIN all_cons_columns col
                                    ON (c.constraint_name = col.constraint_name AND c.owner = col.owner)
                WHERE c2.constraint_type = \'R\'
                  AND c.owner = \'USERNAME\'
                )
                loop
                    execute immediate s.statement;
                end loop;
        end;';

        $this->assertEquals($expected, $statement);
    }

    public function testCompileTables()
    {
        $statement = $this->getGrammar()->compileTables('username');

        $expected = 'select lower(all_tab_comments.table_name)  as "name",
                lower(all_tables.owner) as "schema",
                sum(user_segments.bytes) as "size",
                all_tab_comments.comments as "comments",
                (select lower(value) from nls_database_parameters where parameter = \'NLS_SORT\') as "collation"
            from all_tables
                join all_tab_comments on all_tab_comments.table_name = all_tables.table_name
                left join user_segments on user_segments.segment_name = all_tables.table_name
            where all_tables.owner = \'USERNAME\'
                and all_tab_comments.owner = \'USERNAME\'
                and all_tab_comments.table_type in (\'TABLE\')
            group by all_tab_comments.table_name, all_tables.owner, all_tables.num_rows,
                all_tables.avg_row_len, all_tables.blocks, all_tab_comments.comments
            order by all_tab_comments.table_name';

        $this->assertEquals($expected, $statement);
    }
}
