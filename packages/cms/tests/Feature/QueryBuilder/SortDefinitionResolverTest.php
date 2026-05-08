<?php

use Coda\Cms\QueryBuilder\SortDefinitionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

beforeEach(function () {
    $this->resolver = new SortDefinitionResolver;
});

// --- Pass-through: plain strings ---

it('passes through simple column name strings', function () {
    $sorts = $this->resolver->resolve(['id', 'name']);

    expect($sorts)->toHaveCount(2);
    expect($sorts[0])->toBe('id');
    expect($sorts[1])->toBe('name');
});

// --- Pass-through: AllowedSort objects ---

it('passes through AllowedSort objects unchanged', function () {
    $sort = AllowedSort::field('updatedAt', 'updated_at');
    $sorts = $this->resolver->resolve([$sort]);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBe($sort);
});

// --- Auto snake_case field alias ---

it('auto-maps camelCase to snake_case column', function () {
    $sorts = $this->resolver->resolve(['updatedAt']);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('updatedAt');
});

it('auto-maps multi-word camelCase', function () {
    $sorts = $this->resolver->resolve(['createdAt']);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('createdAt');
});

it('does not map already snake_case strings', function () {
    $sorts = $this->resolver->resolve(['id', 'name']);

    expect($sorts[0])->toBe('id');
    expect($sorts[1])->toBe('name');
});

// --- Arrow notation: explicit field alias still works ---

it('resolves explicit field alias notation', function () {
    $sorts = $this->resolver->resolve(['updatedAt => updated_at']);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('updatedAt');
});

// --- Multi-column: comma-separated plain columns ---

it('resolves comma-separated columns both default asc', function () {
    $sorts = $this->resolver->resolve(['personName => surname, forename']);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('personName');
});

it('resolves comma-separated columns both explicit desc', function () {
    $sorts = $this->resolver->resolve(['custom => forename desc, surname desc']);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('custom');
});

it('resolves comma-separated columns with mixed directions', function () {
    $sorts = $this->resolver->resolve(['custom => forename asc, surname desc']);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('custom');
});

it('resolves comma-separated columns where missing direction defaults to asc', function () {
    $sorts = $this->resolver->resolve(['custom => forename, surname desc']);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('custom');
});

// --- Multi-column SQL integration ---

it('both columns default to asc', function () {
    Schema::create('sort_test_items', function ($table) {
        $table->id();
        $table->string('surname');
        $table->string('forename');
        $table->timestamps();
    });

    DB::table('sort_test_items')->insert([
        ['surname' => 'Smith', 'forename' => 'John', 'created_at' => now(), 'updated_at' => now()],
        ['surname' => 'Adams', 'forename' => 'Jane', 'created_at' => now(), 'updated_at' => now()],
        ['surname' => 'Adams', 'forename' => 'Bob', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $sorts = $this->resolver->resolve(['personName => surname, forename']);
    $sort = $sorts[0];

    $queryBuilder = QueryBuilder::for(
        (new class extends Model
        {
            protected $table = 'sort_test_items';
        })::query()
    );
    $sort->sort($queryBuilder, false);

    $items = $queryBuilder->get();

    expect($items[0]->surname)->toBe('Adams');
    expect($items[0]->forename)->toBe('Bob');
    expect($items[1]->forename)->toBe('Jane');
    expect($items[2]->surname)->toBe('Smith');
});

it('descending flips all column directions', function () {
    Schema::create('sort_flip_items', function ($table) {
        $table->id();
        $table->string('surname');
        $table->string('forename');
        $table->timestamps();
    });

    DB::table('sort_flip_items')->insert([
        ['surname' => 'Smith', 'forename' => 'John', 'created_at' => now(), 'updated_at' => now()],
        ['surname' => 'Adams', 'forename' => 'Jane', 'created_at' => now(), 'updated_at' => now()],
        ['surname' => 'Adams', 'forename' => 'Bob', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $sorts = $this->resolver->resolve(['personName => surname, forename']);
    $sort = $sorts[0];

    $queryBuilder = QueryBuilder::for(
        (new class extends Model
        {
            protected $table = 'sort_flip_items';
        })::query()
    );
    $sort->sort($queryBuilder, true);

    $items = $queryBuilder->get();

    expect($items[0]->surname)->toBe('Smith');
    expect($items[1]->surname)->toBe('Adams');
    expect($items[1]->forename)->toBe('Jane');
    expect($items[2]->forename)->toBe('Bob');
});

it('mixed directions are each flipped independently', function () {
    Schema::create('sort_mixed_items', function ($table) {
        $table->id();
        $table->string('surname');
        $table->string('forename');
        $table->timestamps();
    });

    DB::table('sort_mixed_items')->insert([
        ['surname' => 'Adams', 'forename' => 'Jane', 'created_at' => now(), 'updated_at' => now()],
        ['surname' => 'Adams', 'forename' => 'Bob', 'created_at' => now(), 'updated_at' => now()],
        ['surname' => 'Smith', 'forename' => 'John', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $sorts = $this->resolver->resolve(['custom => surname asc, forename desc']);
    $sort = $sorts[0];

    $queryBuilder = QueryBuilder::for(
        (new class extends Model
        {
            protected $table = 'sort_mixed_items';
        })::query()
    );
    $sort->sort($queryBuilder, false);

    $items = $queryBuilder->get();

    expect($items[0]->surname)->toBe('Adams');
    expect($items[0]->forename)->toBe('Jane');
    expect($items[1]->forename)->toBe('Bob');
    expect($items[2]->surname)->toBe('Smith');
});

it('missing direction defaults to asc', function () {
    Schema::create('sort_default_items', function ($table) {
        $table->id();
        $table->string('surname');
        $table->string('forename');
        $table->timestamps();
    });

    DB::table('sort_default_items')->insert([
        ['surname' => 'Adams', 'forename' => 'Jane', 'created_at' => now(), 'updated_at' => now()],
        ['surname' => 'Adams', 'forename' => 'Bob', 'created_at' => now(), 'updated_at' => now()],
        ['surname' => 'Smith', 'forename' => 'John', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $sorts = $this->resolver->resolve(['custom => surname, forename desc']);
    $sort = $sorts[0];

    $queryBuilder = QueryBuilder::for(
        (new class extends Model
        {
            protected $table = 'sort_default_items';
        })::query()
    );
    $sort->sort($queryBuilder, false);

    $items = $queryBuilder->get();

    expect($items[0]->surname)->toBe('Adams');
    expect($items[0]->forename)->toBe('Jane');
    expect($items[1]->forename)->toBe('Bob');
    expect($items[2]->surname)->toBe('Smith');
});

// --- Local concat: column.concat("other") on same table ---

function createExpressionTables(): void
{
    Schema::create('expr_users', function ($table) {
        $table->id();
        $table->string('surname');
        $table->string('forename');
        $table->foreignId('owner_id')->nullable();
        $table->timestamps();
    });

    Schema::create('expr_categories', function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('expr_exercises', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('type')->nullable();
        $table->foreignId('owner_id')->nullable();
        $table->foreignId('category_id')->nullable();
        $table->timestamps();
    });
}

function exprExerciseModel(): Model
{
    return new class extends Model
    {
        protected $table = 'expr_exercises';

        public function owner(): BelongsTo
        {
            return $this->belongsTo(
                new class extends Model
                {
                    protected $table = 'expr_users';
                }::class,
                'owner_id'
            );
        }

        public function category(): BelongsTo
        {
            return $this->belongsTo(
                new class extends Model
                {
                    protected $table = 'expr_categories';
                }::class,
                'category_id'
            );
        }
    };
}

function exprUserModel(): Model
{
    return new class extends Model
    {
        protected $table = 'expr_users';

        public function owner(): BelongsTo
        {
            return $this->belongsTo(
                new class extends Model
                {
                    protected $table = 'expr_users';
                }::class,
                'owner_id'
            );
        }
    };
}

it('resolves local concat expression', function () {
    createExpressionTables();
    $model = exprUserModel();
    $resolver = new SortDefinitionResolver($model);

    $sorts = $resolver->resolve(['personName => surname.concat("forename")']);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('personName');
});

it('local concat orders correctly', function () {
    createExpressionTables();

    DB::table('expr_users')->insert([
        ['surname' => 'Smith', 'forename' => 'John', 'owner_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ['surname' => 'Adams', 'forename' => 'Jane', 'owner_id' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $model = exprUserModel();
    $resolver = new SortDefinitionResolver($model);
    $sorts = $resolver->resolve(['personName => surname.concat("forename") asc']);
    $sort = $sorts[0];

    $queryBuilder = QueryBuilder::for($model::query());
    $sort->sort($queryBuilder, false);

    $items = $queryBuilder->get();

    expect($items[0]->surname)->toBe('Adams');
    expect($items[1]->surname)->toBe('Smith');
});

// --- Relationship single column: relationship.column ---

it('resolves relationship single column sort', function () {
    createExpressionTables();
    $model = exprExerciseModel();
    $resolver = new SortDefinitionResolver($model);

    $sorts = $resolver->resolve(['category => category.name']);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('category');
});

it('relationship single column orders correctly', function () {
    createExpressionTables();

    $catA = DB::table('expr_categories')->insertGetId(['name' => 'Alpha', 'created_at' => now(), 'updated_at' => now()]);
    $catZ = DB::table('expr_categories')->insertGetId(['name' => 'Zulu', 'created_at' => now(), 'updated_at' => now()]);

    DB::table('expr_exercises')->insert([
        ['name' => 'Exercise Z', 'type' => null, 'category_id' => $catZ, 'owner_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Exercise A', 'type' => null, 'category_id' => $catA, 'owner_id' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $model = exprExerciseModel();
    $resolver = new SortDefinitionResolver($model);
    $sorts = $resolver->resolve(['category => category.name']);
    $sort = $sorts[0];

    $queryBuilder = QueryBuilder::for($model::query());
    $sort->sort($queryBuilder, false);

    $items = $queryBuilder->get();

    expect($items[0]->name)->toBe('Exercise A');
    expect($items[1]->name)->toBe('Exercise Z');
});

// --- Relationship concat: relationship.column.concat("other") ---

it('resolves relationship concat expression', function () {
    createExpressionTables();
    $model = exprExerciseModel();
    $resolver = new SortDefinitionResolver($model);

    $sorts = $resolver->resolve(['coach => owner.surname.concat("forename")']);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('coach');
});

it('relationship concat orders correctly', function () {
    createExpressionTables();

    $userA = DB::table('expr_users')->insertGetId(['surname' => 'Adams', 'forename' => 'Jane', 'owner_id' => null, 'created_at' => now(), 'updated_at' => now()]);
    $userS = DB::table('expr_users')->insertGetId(['surname' => 'Smith', 'forename' => 'John', 'owner_id' => null, 'created_at' => now(), 'updated_at' => now()]);

    DB::table('expr_exercises')->insert([
        ['name' => 'Exercise by Smith', 'type' => null, 'owner_id' => $userS, 'category_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Exercise by Adams', 'type' => null, 'owner_id' => $userA, 'category_id' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $model = exprExerciseModel();
    $resolver = new SortDefinitionResolver($model);
    $sorts = $resolver->resolve(['coach => owner.surname.concat("forename")']);
    $sort = $sorts[0];

    $queryBuilder = QueryBuilder::for($model::query());
    $sort->sort($queryBuilder, false);

    $items = $queryBuilder->get();

    expect($items[0]->name)->toBe('Exercise by Adams');
    expect($items[1]->name)->toBe('Exercise by Smith');
});

// --- Mixed: comma-separated with relationship and local expressions ---

it('resolves mixed comma-separated with relationship expression', function () {
    createExpressionTables();
    $model = exprExerciseModel();
    $resolver = new SortDefinitionResolver($model);

    $sorts = $resolver->resolve(['combined => category.name, owner.surname.concat("forename")']);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('combined');
});

it('resolves mixed comma-separated with local column and relationship', function () {
    createExpressionTables();
    $model = exprExerciseModel();
    $resolver = new SortDefinitionResolver($model);

    $sorts = $resolver->resolve(['mixed => name, category.name']);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('mixed');
});

// --- Full integration: all sort types in one array ---

it('resolves all sort types together', function () {
    createExpressionTables();
    $model = exprExerciseModel();
    $resolver = new SortDefinitionResolver($model);

    $sorts = $resolver->resolve([
        'id',
        'updatedAt => updated_at',
        'personName => surname.concat("forename") asc',
        'coach => owner.surname.concat("forename")',
        'category => category.name',
        AllowedSort::field('createdAt', 'created_at'),
    ]);

    expect($sorts)->toHaveCount(6);
    expect($sorts[0])->toBe('id');
    expect($sorts[1])->toBeInstanceOf(AllowedSort::class)->and($sorts[1]->getName())->toBe('updatedAt');
    expect($sorts[2])->toBeInstanceOf(AllowedSort::class)->and($sorts[2]->getName())->toBe('personName');
    expect($sorts[3])->toBeInstanceOf(AllowedSort::class)->and($sorts[3]->getName())->toBe('coach');
    expect($sorts[4])->toBeInstanceOf(AllowedSort::class)->and($sorts[4]->getName())->toBe('category');
    expect($sorts[5])->toBeInstanceOf(AllowedSort::class)->and($sorts[5]->getName())->toBe('createdAt');
});

// --- Legacy: relation() syntax still works ---

it('resolves legacy relation() syntax', function () {
    $sorts = $this->resolver->resolve(['category => relation(tags, name, category_id)']);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('category');
});

it('resolves legacy relation() with table qualifier', function () {
    $sorts = $this->resolver->resolve([
        "coach => relation(users, \"CONCAT(surname, ' ', forename)\", owner_id, exercises)",
    ]);

    expect($sorts)->toHaveCount(1);
    expect($sorts[0])->toBeInstanceOf(AllowedSort::class);
    expect($sorts[0]->getName())->toBe('coach');
});
