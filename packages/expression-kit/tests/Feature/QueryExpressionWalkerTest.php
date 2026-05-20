<?php

use Coda\ExpressionKit\Contracts\ExpressionResolver;
use Coda\ExpressionKit\QueryExpressionWalker;
use Coda\ExpressionKit\ResolvedExpressionReference;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;

beforeEach(function () {
    $schema = expressionCapsule()->schema();

    $schema->dropIfExists('expression_test_comments');
    $schema->dropIfExists('expression_test_articles');
    $schema->dropIfExists('expression_test_authors');

    $schema->create('expression_test_authors', function ($table) {
        $table->id();
        $table->string('surname');
        $table->timestamps();
    });

    $schema->create('expression_test_articles', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('status')->default('draft');
        $table->boolean('featured')->default(false);
        $table->foreignId('author_id')->nullable();
        $table->timestamps();
    });

    $schema->create('expression_test_comments', function ($table) {
        $table->id();
        $table->foreignId('article_id');
        $table->timestamps();
    });
});

function expressionCapsule(): Capsule
{
    static $capsule = null;

    if ($capsule instanceof Capsule) {
        return $capsule;
    }

    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    Model::unguard();

    return $capsule;
}

function expressionArticleModel(): Model
{
    return new class extends Model
    {
        protected $table = 'expression_test_articles';

        public function author(): BelongsTo
        {
            return $this->belongsTo(
                new class extends Model
                {
                    protected $table = 'expression_test_authors';
                }::class,
                'author_id'
            );
        }
    };
}

function expressionResolver(): ExpressionResolver
{
    return new class implements ExpressionResolver
    {
        public function names(): array
        {
            return [
                'title',
                'status',
                'featured',
                'author',
                'comment_count',
                'onboarded',
            ];
        }

        public function resolve(Node $node): ResolvedExpressionReference
        {
            $path = expressionNodePath($node);

            return match ($path) {
                'title' => ResolvedExpressionReference::field('title'),
                'status' => ResolvedExpressionReference::field('status'),
                'featured' => ResolvedExpressionReference::field('featured'),
                'author' => ResolvedExpressionReference::relationship('author'),
                'author.surname' => ResolvedExpressionReference::relationship('author', 'surname'),
                'comment_count' => ResolvedExpressionReference::subquery(
                    expressionCapsule()->table('expression_test_comments')
                        ->selectRaw('count(*)')
                        ->whereColumn('expression_test_comments.article_id', 'expression_test_articles.id')
                ),
                'onboarded' => ResolvedExpressionReference::expression(
                    'status == "published" and featured == true',
                    'onboarded',
                ),
                default => throw new InvalidArgumentException("Unknown path [{$path}]."),
            };
        }
    };
}

function expressionNodePath(Node $node): ?string
{
    if ($node instanceof NameNode) {
        return $node->attributes['name'];
    }

    if (! $node instanceof GetAttrNode || $node->attributes['type'] !== GetAttrNode::PROPERTY_CALL) {
        return null;
    }

    $left = expressionNodePath($node->nodes['node']);
    $attribute = $node->nodes['attribute']->attributes['value'] ?? null;

    if (! is_string($left) || ! is_string($attribute)) {
        return null;
    }

    return $left.'.'.$attribute;
}

function expressionAppliedTitles(string $expression): array
{
    $query = expressionArticleModel()->newQuery();
    $walker = new QueryExpressionWalker(expressionResolver());
    $walker->apply($query, $expression);

    return $query->orderBy('id')->pluck('title')->all();
}

it('applies bare expression-backed paths', function () {
    $authorId = expressionCapsule()->table('expression_test_authors')->insertGetId([
        'surname' => 'Adams',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expressionCapsule()->table('expression_test_articles')->insert([
        [
            'title' => 'Published and featured',
            'status' => 'published',
            'featured' => true,
            'author_id' => $authorId,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'title' => 'Draft',
            'status' => 'draft',
            'featured' => true,
            'author_id' => $authorId,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    expect(expressionAppliedTitles('onboarded'))->toBe(['Published and featured']);
    expect(expressionAppliedTitles('onboarded == false'))->toBe(['Draft']);
});

it('applies subquery-backed comparisons', function () {
    $firstArticle = expressionCapsule()->table('expression_test_articles')->insertGetId([
        'title' => 'Busy article',
        'status' => 'published',
        'featured' => false,
        'author_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $secondArticle = expressionCapsule()->table('expression_test_articles')->insertGetId([
        'title' => 'Quiet article',
        'status' => 'published',
        'featured' => false,
        'author_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expressionCapsule()->table('expression_test_comments')->insert([
        ['article_id' => $firstArticle, 'created_at' => now(), 'updated_at' => now()],
        ['article_id' => $firstArticle, 'created_at' => now(), 'updated_at' => now()],
        ['article_id' => $secondArticle, 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(expressionAppliedTitles('comment_count > 1'))->toBe(['Busy article']);
});

it('applies relationship existence and nested relationship fields', function () {
    $adams = expressionCapsule()->table('expression_test_authors')->insertGetId([
        'surname' => 'Adams',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $smith = expressionCapsule()->table('expression_test_authors')->insertGetId([
        'surname' => 'Smith',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expressionCapsule()->table('expression_test_articles')->insert([
        [
            'title' => 'Has Adams author',
            'status' => 'published',
            'featured' => false,
            'author_id' => $adams,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'title' => 'Has Smith author',
            'status' => 'published',
            'featured' => false,
            'author_id' => $smith,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'title' => 'No author',
            'status' => 'published',
            'featured' => false,
            'author_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    expect(expressionAppliedTitles('author'))->toBe(['Has Adams author', 'Has Smith author']);
    expect(expressionAppliedTitles('author.surname == "Adams"'))->toBe(['Has Adams author']);
});
