# sexlog/elasticsearch-query-builder

This is a library to query data from ElasticSearch. I have just started the development of this library and there is a lot to be done.

My aim is to communicate with ElasticSearch in a fully object oriented way.
  
## Examples
  
### Basic query  
  
```php
    // Connection is created on object instatiation
    $hosts = ['10.0.0.10:9200'];
    $index = 'products';
    $type = 'product';
    
    $elasticSearch = new ElasticSearch\ElasticSearch($hosts, $index, $type);

    // SELECT * FROM products WHERE product_name = 'ElasticSearch' LIMIT 4
    $query = new ElasticSearch\Query();
    $query->where('product_name', 'ElasticSearch');

    $elasticSearch->setQuery($query)->take(4)->get();

    // Paging results
    $elasticSearch->page(1)->get();
    $elasticSearch->page(2)->get();
```

### Query with a Filter

```php
    /*
     * SELECT id, product_name, price, updated_at 
     * FROM products 
     * WHERE product_name LIKE 'car%' AND category = 3 LIMIT 20 OFFSET 0
     */ 
    $query = new ElasticSearch\Query();
    $query->wildcard('product_name', 'car*');

    $filter = new ElasticSearch\Filter();
    $filter->where('category', 3);

    // You should always use the take method before paging
    $elasticSearch->select('id, product_name, price, updated_at')
                  ->setQuery($query)
                  ->setFilter($filter)
                  ->take(20)
                  ->page(0)
                  ->get();
    
    // Paging
    $elasticSearch->page(1)->get(); 
```
