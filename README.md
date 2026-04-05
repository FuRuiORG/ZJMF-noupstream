# ZJMF-noupstream

[![PHP Version](https://img.shields.io/badge/PHP-7.2%20--%207.4-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![ZJMF](https://img.shields.io/badge/ZJMF-Compatible-orange.svg)](https://www.zjmf.cn/)

> 修复魔方财务系统（ZJMF）/api/product/prodetail接口泄露对接上游信息的轻量级安全补丁

## 目录

- [功能特性](#功能特性)
- [问题背景](#问题背景)
- [快速开始](#快速开始)
- [安装方法](#安装方法)
- [拦截字段列表](#拦截字段列表)
- [技术原理](#技术原理)
- [效果对比](#效果对比)
- [兼容性](#兼容性)
- [常见问题](#常见问题)
- [贡献指南](#贡献指南)
- [许可证](#许可证)

## 功能特性

- **精准拦截** - 自动识别并隐藏15个上游敏感字段
- **零配置** - 即插即用，无需修改业务代码
- **全局覆盖** - 基于输出缓冲，拦截所有JSON响应
- **深度清理** - 递归遍历多层嵌套数据结构
- **兼容压缩** - 自动处理Gzip/Deflate压缩响应
- **高性能** - 轻量级实现，对性能影响<1ms
- **安全可靠** - 不影响正常业务逻辑

## 问题背景

### 安全隐患

魔方财务系统（ZJMF）在使用上游代销模式时，`/api/product/prodetail` 接口会返回以下敏感信息：

```json
{
  "api_type": "zjmf_api",
  "upstream_product_shopping_url": "https://upstream.com/cart?action=configureproduct&amp;pid=1564",
  "upstream_pid": 1564,
  "zjmf_api_id": 1,
  "upstream_version": 5,
  ...
}
```

### 泄露风险

| 风险类型 | 说明 | 危害等级 |
|---------|------|---------|
| 上游URL泄露 | 客户可直接访问上游商家 | 高 |
| 价格体系暴露 | 可推算进货价格 | 高 |
| 商业模式暴露 | 识别代销模式降低信任度 | 中 |

## 安装方法

### 方法一：直接复制（推荐）

仓库根目录已提供配置好的 `index.php` 文件，包含完整的拦截器引入代码，可直接复制使用：

```bash
# 1. 复制 upstream_hide.php 到项目根目录
cp upstream_hide.php /path/to/your-zjmf/
<img width="474" height="539" alt="image" src="https://github.com/user-attachments/assets/42697264-bba6-4181-88bf-6e62c28fac2c" />


# 2. 直接用仓库中的 index.php 替换原文件
cp index.php /path/to/your-zjmf/public/index.php
<img width="534" height="647" alt="image" src="https://github.com/user-attachments/assets/4f0c430b-d058-4bfe-b3de-d3b38a9ef523" />


# 3. 完成！无需手动修改任何代码
```

**文件说明：**

| 文件 | 用途 | 操作 |
|------|------|------|
| `upstream_hide.php` | 核心拦截器 | 复制到项目**根目录** |
| `index.php` | 已修改的入口文件 | 复制到项目 **public/** 目录替换原文件 |

**注意：** 替换前请备份原有的 `public/index.php` 文件

### 方法二：手动修改

如果需要保留原有 `index.php` 的自定义配置，可以手动添加代码：

#### Step 1: 复制拦截器文件

将 `upstream_hide.php` 放置在魔方财务系统根目录：
<img width="474" height="539" alt="image" src="https://github.com/user-attachments/assets/42697264-bba6-4181-88bf-6e62c28fac2c" />

```
your-zjmf-installation/
├── app/
├── public/
│   └── index.php          ← 修改此文件
├── upstream_hide.php      ← 新增此文件
├── data/
└── ...
```

#### Step 2: 修改入口文件

编辑 `public/index.php`，在 **require base.php 后** 添加一行代码：
<img width="534" height="647" alt="image" src="https://github.com/user-attachments/assets/4f0c430b-d058-4bfe-b3de-d3b38a9ef523" />

```php
// 加载基础文件
require CMF_ROOT . 'vendor/thinkphp/base.php';

// 加载上游信息隐藏拦截器 (ZJMF-noupstream)
require CMF_ROOT . 'upstream_hide.php';  // ← 新增这行

// 执行应用并响应
Container::get('app', [APP_PATH])->run()->send();
```
<img width="505" height="217" alt="image" src="https://github.com/user-attachments/assets/76658977-a645-4881-8bd3-cb9be5b22fa0" />


#### Step 3: 验证安装

访问任意产品详情API，检查返回数据：

```bash
curl -s "https://your-domain.com/api/product/prodetail?pids[0]=1" | jq '.data.detail["1"] | {api_type, upstream_pid, upstream_product_shopping_url}'
```

**预期结果（已隐藏）：**
```json
{
  "api_type": "normal",
  "upstream_pid": 0,
  "upstream_product_shopping_url": null
}
```

## 拦截字段列表

### 核心敏感字段（14个）

| 字段名 | 原始值示例 | 替换后 | 说明 |
|-------|-----------|--------|------|
| `api_type` | `"zjmf_api"` | `"normal"` | 伪装为自营模式 |
| `upstream_product_shopping_url` | `"https://..."` | `null` | 删除上游购买链接 |
| `upstream_pid` | `1564` | `0` | 清除上游产品ID |
| `zjmf_api_id` | `1` | `0` | 清除API接口ID |
| `upstream_version` | `5` | `0` | 清除上游版本号 |
| `upstream_price_type` | `"percent"` | `null` | 清除计价方式 |
| `upstream_price_value` | `"110.00"` | `null` | 清除加价比例 |
| `upstream_qty` | `100` | `0` | 清除上游库存 |
| `upstream_stock_control` | `1` | `0` | 关闭库存同步 |
| `upstream_ontrial_status` | `1` | `0` | 清除试用状态 |
| `upstream_price` | `"25.00"` | `"0.00"` | 清除上游原价 |
| `upstream_cycle` | `"monthly"` | `""` | 清除计费周期 |
| `upstream_auto_setup` | `"payment"` | `""` | 清除自动开通设置 |
| `location_version` | `160` | `0` | 清除位置版本 |

### 额外保护字段（2个）

| 字段名 | 触发条件 | 替换规则 |
|-------|---------|---------|
| `upstream_id` | 值 ≠ 0 | 强制设为 `0` |
| `upper_reaches_id` | 值 ≠ 0 | 强制设为 `0` |

## 技术原理

### 工作流程图

```
用户请求
    ↓
┌─────────────────────────────────────┐
│   public/index.php                  │
│   ├─ 加载 ThinkPHP 框架             │
│   ├─ 引入 upstream_hide.php         │  ← ob_start() 启动缓冲
│   └─ $app->run()->send()           │
└─────────────────┬───────────────────┘
                  ↓
        应用程序执行业务逻辑
        生成 JSON 响应数据
                  ↓
┌─────────────────────────────────────┐
│   upstreamHideFilter() 回调函数     │
│                                     │
│   1. 检测响应内容类型               │
│      ├─ JSON对象 ({...})            │
│      ├─ JSON数组 [...]              │
│      └─ Gzip压缩内容                │
│                                     │
│   2. 解析JSON → PHP数组            │
│                                     │
│   3. 递归调用 CleanArray()          │
│      └─ 遍历所有层级：              │
│         data.detail.{pid}.config    │
│         _groups.options.sub.pricings│
│                                     │
│   4. 匹配并替换敏感字段             │
│                                     │
│   5. 重新编码为JSON                 │
│      └─ 保持Unicode/格式不变        │
└─────────────────┬───────────────────┘
                  ↓
        返回净化后的响应给用户
```

### 核心算法

```php
function upstreamHideCleanArray(&$data, $hideFields, $replaceValues) {
    // 1. 递归基例：非数组直接返回
    if (!is_array($data)) return;
    
    // 2. 遍历每个键值对
    foreach ($data as $key => &$value) {
        // 3. 深度优先：先处理子节点
        if (is_array($value)) {
            upstreamHideCleanArray($value, $hideFields, $replaceValues);
        }
        
        // 4. 字段匹配与替换
        $strKey = (string)$key;
        if (in_array($strKey, $hideFields, true)) {
            // 特殊处理：购物URL设为null
            if ($strKey === 'upstream_product_shopping_url') {
                $data[$key] = null;
            }
            // 通用替换：使用预定义映射表
            elseif (isset($replaceValues[$strKey])) {
                $data[$key] = $replaceValues[$strKey];
            }
            // 默认行为：删除字段
            else {
                unset($data[$key]);
            }
        }
        
        // 5. 额外保护：清零关联ID
        if ($strKey === 'upstream_id' && $value != 0) {
            $data[$key] = 0;
        }
    }
}
```

### 压缩处理机制

针对服务器开启Gzip压缩的场景：

```php
function upstreamHideTryDecompress($buffer) {
    // 检测Gzip格式 (0x1f 0x8b)
    if ($b0 === 0x1f && $b1 === 0x8b) {
        return @gzdecode($buffer);
    }
    
    // 检测Zlib格式 (0x78 0x01/5e/9c)
    if ($b0 === 0x78 && ...) {
        return @gzuncompress($buffer);
    }
    
    // PHP版本兼容处理
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        // PHP 7.2-7.3: 使用inflate_init (如果可用)
        return @inflate_add(@inflate_init(...), $buffer);
    } else {
        // PHP 7.4+: 直接使用gzinflate
        return @gzinflate($buffer);
    }
}
```

## 效果对比

以下数据来自真实环境测试，产品：`香港三网直连 2核 4GB 500Mbps 限制流量` (ID: 2)

### 拦截前原始响应（部分）

```json
{
  "id": 2,
  "name": "香港三网直连 2核 4GB 500Mbps 限制流量",
  "api_type": "zjmf_api",
  "location_version": 2,
  "upstream_version": 5,
  "upstream_price_type": "percent",
  "upstream_price_value": "110.00",
  "zjmf_api_id": 1,
  "upstream_pid": 1564,
  "product_shopping_url": "http://xxx.com/cart?action=configureproduct&pid=2",
  "upstream_qty": 0,
  "upstream_product_shopping_url": "https://upstream.com/cart?action=configureproduct&amp;pid=1564",
  "upstream_stock_control": 0,
  "upstream_auto_setup": "payment",
  "upstream_ontrial_status": 0,
  "upstream_price": "0.00",
  "upstream_cycle": ""

```

### 拦截后响应（现.json）

```json
{
  "id": 2,
  "name": "香港三网直连 2核 4GB 500Mbps 限制流量",
  "api_type": "normal",
  "location_version": 0,
  "upstream_version": 0,
  "zjmf_api_id": 0,
  "upstream_pid": 0,
  "product_shopping_url": "http://xxx.com/cart?action=configureproduct&pid=2",
  "upstream_qty": 0,
  "upstream_product_shopping_url": null,
  "upstream_stock_control": 0,
  "upstream_auto_setup": "",
  "upstream_ontrial_status": 0,
  "upstream_price": "0.00",
  "upstream_cycle": ""
}
```

### 字段变化对照表

| 字段名 | 拦截前 | 拦截后 | 状态 |
|-------|--------|--------|------|
| `api_type` | `"zjmf_api"` | `"normal"` | 已替换 |
| `location_version` | `2` | `0` | 已清除 |
| `upstream_version` | `5` | `0` | 已清除 |
| `upstream_price_type` | `"percent"` | *(已删除)* | 已移除 |
| `upstream_price_value` | `"110.00"` | *(已删除)* | 已移除 |
| `zjmf_api_id` | `1` | `0` | 已清除 |
| `upstream_pid` | `1564` | `0` | 已清除 |
| `upstream_product_shopping_url` | `"https://ly.stay33.com/..."` | `null` | 已删除 |
| `upstream_auto_setup` | `"payment"` | `""` | 已清空 |

**注意：** `product_shopping_url`（自有购物链接）保持不变，仅删除了上游链接

## 兼容性要求
**魔方财务可以运行该脚本即可运行**

## 常见问题

### Q1: 是否会影响网站性能？

**答：** 影响极小。性能损耗主要来自：
- JSON解析：< 0.5ms
- 数组遍历：< 0.3ms（取决于数据量）
- 重新编码：< 0.2ms

**总耗时 < 1ms**，对用户体验无感知影响。

### Q2: 会影响非API请求吗？

**答：** 不会。脚本会智能检测：
- 仅处理 `{...}` 或 `[...]` 格式的JSON响应
- 自动跳过HTML页面、图片、CSS/JS静态资源
- 如果响应不是有效JSON，原样返回不做任何修改

### Q3: 如何临时禁用？

**答：** 注释掉 `public/index.php` 中的引入行即可：

```php
// require CMF_ROOT . 'upstream_hide.php';  // 临时禁用
```

### Q4: 能否自定义要隐藏的字段？

**答：** 可以！编辑 `upstream_hide.php` 中的两个配置数组：

```php
// 添加新的隐藏字段
$upstreamHideFields[] = 'your_custom_field';

// 定义替换值
$upstreamReplaceValues['your_custom_field'] = 'safe_value';
```

### Q5: 支持哪些API接口？

**答：** 由于基于全局输出缓冲，理论上支持**所有返回JSON的接口**，包括但不限于：

- `/api/product/prodetail` - 产品详情
- 以及其他所有API端点

### Q6: 数据库中的原始数据会被修改吗？

**答：** 不会。本工具仅在**输出层**进行拦截，不修改任何数据库记录或源代码逻辑。停止使用后，系统完全恢复原状。

## 许可证

本项目采用 [MIT License](LICENSE) 开源协议。

## 致谢

- 感谢魔方财务（ZJMF）团队提供的优秀主机销售系统
- 感谢所有贡献者和使用者
- 感谢开源社区的反馈和建议

## 联系方式

- QQ: 2024659553
- QQ群: 311971756
- Issues: [GitHub Issues](../../issues)
- Discussions: [GitHub Discussions](../../discussions)

---

<div align="center">

**如果这个项目对你有帮助，请给一个Star支持！**

Developed by [FuRuiORG](https://github.com/FuRuiORG) / RuiNexus

</div>
