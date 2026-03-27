<?php

use Coda\Cms\QueryBuilder\QueryExpressionWalker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('articles', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('status')->default('draft');
        $table->string('type')->nullable();
        $table->integer('year')->default(2024);
        $table->boolean('featured')->default(false);
        $table->foreignId('author_id')->nullable();
        $table->foreignId('category_id')->nullable();
        $table->timestamps();
    });

    Schema::create('authors', function ($table) {
        $table->id();
        $table->string('surname');
        $table->string('forename');
        $table->foreignId('team_id')->nullable();
        $table->timestamps();
    });

    Schema::create('categories', function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('teams', function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $this->walker = new QueryExpressionWalker;
});

function articleQuery(): Builder
{
    return (new class extends Model
    {
        protected $table = 'articles';

        public function author(): BelongsTo
        {
            return $this->belongsTo(
                new class extends Model
                {
                    protected $table = 'authors';

                    public function team(): BelongsTo
                    {
                        return $this->belongsTo(
                            new class extends Model
                            {
                                protected $table = 'teams';
                            }::class
                        );
                    }
                }::class
            );
        }

        public function category(): BelongsTo
        {
            return $this->belongsTo(
                new class extends Model
                {
                    protected $table = 'categories';
                }::class
            );
        }
    })::query();
}

function whitelist(): array
{
    return [
        'fields' => ['title', 'status', 'type', 'year', 'featured'],
        'relationships' => [
            'author' => ['surname', 'forename'],
            'category' => ['name'],
        ],
    ];
}

function appliedSql(string $expression, ?array $fieldWhitelist = null, mixed $value = null): string
{
    $query = articleQuery();
    $walker = new QueryExpressionWalker;
    $walker->apply($query, $expression, $fieldWhitelist ?? whitelist(), $value);

    return $query->toRawSql();
}

// --- Basic comparison operators ---

it('applies equals condition', function () {
    $sql = appliedSql('status == "published"');

    expect($sql)->toContain('where')
        ->toContain('status')
        ->toContain('published');
});

it('applies not equals condition', function () {
    $sql = appliedSql('status != "draft"');

    expect($sql)->toContain('where')
        ->toContain('status')
        ->toContain('draft');
});

it('applies greater than condition', function () {
    $sql = appliedSql('year > 2020');

    expect($sql)->toContain('where')
        ->toContain('year')
        ->toContain('2020');
});

it('applies greater than or equal condition', function () {
    $sql = appliedSql('year >= 2020');

    expect($sql)->toContain('where')
        ->toContain('year')
        ->toContain('2020');
});

it('applies less than condition', function () {
    $sql = appliedSql('year < 2025');

    expect($sql)->toContain('where')
        ->toContain('year')
        ->toContain('2025');
});

it('applies less than or equal condition', function () {
    $sql = appliedSql('year <= 2025');

    expect($sql)->toContain('where')
        ->toContain('year')
        ->toContain('2025');
});

// --- Boolean values ---

it('applies equals with boolean true', function () {
    $sql = appliedSql('featured == true');

    expect($sql)->toContain('where')
        ->toContain('featured');
});

it('applies equals with boolean false', function () {
    $sql = appliedSql('featured == false');

    expect($sql)->toContain('where')
        ->toContain('featured');
});

// --- in / not in ---

it('applies in operator', function () {
    $sql = appliedSql('status in ["published", "archived"]');

    expect($sql)->toContain('status')
        ->toContain('published')
        ->toContain('archived');
});

it('applies not in operator', function () {
    $sql = appliedSql('status not in ["draft", "deleted"]');

    expect($sql)->toContain('status')
        ->toContain('draft')
        ->toContain('deleted');
});

// --- String operators ---

it('applies contains operator', function () {
    $sql = appliedSql('title contains "Laravel"');

    expect($sql)->toContain('title')
        ->toContain('like')
        ->toContain('%Laravel%');
});

it('applies starts with operator', function () {
    $sql = appliedSql('title starts with "Getting"');

    expect($sql)->toContain('title')
        ->toContain('like')
        ->toContain('Getting%');
});

it('applies ends with operator', function () {
    $sql = appliedSql('title ends with "Guide"');

    expect($sql)->toContain('title')
        ->toContain('like')
        ->toContain('%Guide');
});

// --- Logical operators ---

it('applies and operator', function () {
    $sql = appliedSql('status == "published" and year >= 2024');

    expect($sql)->toContain('status')
        ->toContain('published')
        ->toContain('year')
        ->toContain('2024');
});

it('applies or operator with correct grouping', function () {
    $sql = appliedSql('status == "published" or status == "archived"');

    expect($sql)->toContain('status')
        ->toContain('published')
        ->toContain('archived')
        ->toContain('or');
});

it('handles nested or inside and', function () {
    $sql = appliedSql('year >= 2024 and (status == "published" or status == "archived")');

    expect($sql)->toContain('year')
        ->toContain('2024')
        ->toContain('status')
        ->toContain('published')
        ->toContain('archived');
});

it('handles nested and inside or', function () {
    $sql = appliedSql('(status == "published" and year >= 2024) or (status == "draft" and featured == true)');

    expect($sql)->toContain('published')
        ->toContain('2024')
        ->toContain('draft')
        ->toContain('featured');
});

// --- Unary not ---

it('applies not operator on simple condition', function () {
    $sql = appliedSql('not (status == "draft")');

    expect($sql)->toContain('status');
});

it('applies not operator on compound condition', function () {
    $sql = appliedSql('not (status == "draft" and year < 2024)');

    expect($sql)->toContain('status')
        ->toContain('year');
});

// --- $value placeholder ---

it('substitutes $value placeholder with runtime value', function () {
    $sql = appliedSql('title contains $value', null, 'Laravel');

    expect($sql)->toContain('title')
        ->toContain('like')
        ->toContain('%Laravel%');
});

it('substitutes $value in or expression across multiple columns', function () {
    $sql = appliedSql('title contains $value or type contains $value', null, 'Training');

    expect($sql)->toContain('title')
        ->toContain('type')
        ->toContain('%Training%');
});

// --- Relationship traversal ---

it('applies relationship filter with dot notation', function () {
    $sql = appliedSql('author.surname == "Smith"');

    expect($sql)->toContain('author')
        ->toContain('surname')
        ->toContain('Smith');
});

it('applies relationship filter with contains', function () {
    $sql = appliedSql('author.forename contains "Jo"');

    expect($sql)->toContain('author')
        ->toContain('forename')
        ->toContain('%Jo%');
});

it('applies relationship filter with in operator', function () {
    $sql = appliedSql('category.name in ["Sports", "Fitness"]');

    expect($sql)->toContain('category')
        ->toContain('name')
        ->toContain('Sports')
        ->toContain('Fitness');
});

it('combines relationship and direct field filters', function () {
    $sql = appliedSql('status == "published" and author.surname == "Smith"');

    expect($sql)->toContain('status')
        ->toContain('published')
        ->toContain('author')
        ->toContain('surname')
        ->toContain('Smith');
});

// --- Whitelist enforcement ---

it('throws on field not in whitelist', function () {
    appliedSql('password == "secret"');
})->throws(InvalidArgumentException::class);

it('throws on relationship not in whitelist', function () {
    appliedSql('unknown.field == "value"');
})->throws(InvalidArgumentException::class);

it('throws on relationship column not in whitelist', function () {
    appliedSql('author.email == "test@example.com"');
})->throws(InvalidArgumentException::class);

// --- Disallowed node types ---

it('throws on function calls', function () {
    appliedSql('upper(title) == "TEST"');
})->throws(InvalidArgumentException::class);

it('throws on arithmetic expressions', function () {
    appliedSql('year + 1 > 2025');
})->throws(InvalidArgumentException::class);

it('throws on ternary/conditional expressions', function () {
    appliedSql('status == "draft" ? true : false');
})->throws(InvalidArgumentException::class);

// --- Integration: actual query results ---

it('filters rows with equals', function () {
    DB::table('articles')->insert([
        ['title' => 'Draft Post', 'status' => 'draft', 'year' => 2024, 'featured' => false],
        ['title' => 'Published Post', 'status' => 'published', 'year' => 2024, 'featured' => true],
    ]);

    $query = articleQuery();
    $this->walker->apply($query, 'status == "published"', whitelist());

    expect($query->count())->toBe(1);
    expect($query->first()->title)->toBe('Published Post');
});

it('filters rows with and', function () {
    DB::table('articles')->insert([
        ['title' => 'Old Published', 'status' => 'published', 'year' => 2020, 'featured' => false],
        ['title' => 'New Published', 'status' => 'published', 'year' => 2025, 'featured' => false],
        ['title' => 'New Draft', 'status' => 'draft', 'year' => 2025, 'featured' => false],
    ]);

    $query = articleQuery();
    $this->walker->apply($query, 'status == "published" and year >= 2024', whitelist());

    expect($query->count())->toBe(1);
    expect($query->first()->title)->toBe('New Published');
});

it('filters rows with or', function () {
    DB::table('articles')->insert([
        ['title' => 'Published', 'status' => 'published', 'year' => 2024, 'featured' => false],
        ['title' => 'Archived', 'status' => 'archived', 'year' => 2024, 'featured' => false],
        ['title' => 'Draft', 'status' => 'draft', 'year' => 2024, 'featured' => false],
    ]);

    $query = articleQuery();
    $this->walker->apply($query, 'status == "published" or status == "archived"', whitelist());

    expect($query->count())->toBe(2);
});

it('filters rows with contains and value placeholder', function () {
    DB::table('articles')->insert([
        ['title' => 'Strength Training', 'status' => 'published', 'year' => 2024, 'featured' => false],
        ['title' => 'Cardio Workout', 'status' => 'published', 'year' => 2024, 'featured' => false],
    ]);

    $query = articleQuery();
    $this->walker->apply($query, 'title contains $value', whitelist(), 'Strength');

    expect($query->count())->toBe(1);
    expect($query->first()->title)->toBe('Strength Training');
});

it('filters rows with in operator', function () {
    DB::table('articles')->insert([
        ['title' => 'A', 'status' => 'draft', 'year' => 2024, 'featured' => false],
        ['title' => 'B', 'status' => 'published', 'year' => 2024, 'featured' => false],
        ['title' => 'C', 'status' => 'archived', 'year' => 2024, 'featured' => false],
    ]);

    $query = articleQuery();
    $this->walker->apply($query, 'status in ["draft", "archived"]', whitelist());

    expect($query->count())->toBe(2);
});
