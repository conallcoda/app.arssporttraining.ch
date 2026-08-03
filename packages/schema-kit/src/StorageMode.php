<?php

namespace Coda\SchemaKit;

enum StorageMode: string
{
    case Attribute = 'attribute';
    case Json = 'json';
    case Normalized = 'normalized';
    case Relation = 'relation';
}
