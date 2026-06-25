<?php

namespace Coda\SchemaKit;

enum QueryStrategy: string
{
    case None = 'none';
    case Fact = 'fact';
    case FullText = 'full_text';
    case AggregateOnly = 'aggregate_only';
}
