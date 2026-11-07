# ZJMF-noupstream

修复魔方财务(ZJMF)`/api/product/prodetail`接口泄露上游信息的补丁，不修改业务代码。

在`public/index.php`加载框架后启动输出缓冲，递归清理JSON响应中的上游字段，兼容Gzip与Deflate。

IDCSMART已在V3.7.9修复该问题，新版请直接使用官方补丁。

## 安装

把`upstream_hide.php`放到魔方财务根目录：

```bash
cp upstream_hide.php /path/to/your-zjmf/
```

用仓库中的`index.php`替换`public/index.php`：

```bash
cp index.php /path/to/your-zjmf/public/index.php
```

也可以手动在`require base.php`后添加一行：

```php
require CMF_ROOT . 'upstream_hide.php';
```

替换前备份原`public/index.php`。

## 验证

```bash
curl -s "https://your-domain.com/api/product/prodetail?pids[0]=1" | jq '.data.detail["1"] | {api_type, upstream_pid, upstream_product_shopping_url}'
```

隐藏后`api_type`为`normal`，`upstream_pid`为`0`，`upstream_product_shopping_url`为`null`。

## 字段

| 字段 | 处理 |
| --- | --- |
| `api_type` | 改为`normal` |
| `upstream_product_shopping_url` | 改为`null` |
| `upstream_pid`、`zjmf_api_id`、`upstream_version`、`upstream_qty`、`upstream_stock_control`、`upstream_ontrial_status` | 改为`0` |
| `upstream_price` | 改为`0.00` |
| `upstream_price_type`、`upstream_price_value` | 删除 |
| `upstream_cycle`、`upstream_auto_setup` | 置空 |
| `upstream_id`、`upper_reaches_id` | 非0时改为0 |

`location_version`保留，下游依赖它同步产品和利润比例。

## 禁用

注释`public/index.php`中的引入行即可。

## License

MIT，见`LICENSE`。
