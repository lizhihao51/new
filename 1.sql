-- 1. 创建数据库（若不存在），指定字符集和排序规则（适配多语言）
CREATE DATABASE IF NOT EXISTS sn_edu 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- 使用目标数据库
USE sn_edu;

-- 2. 创建users表（用户表）
CREATE TABLE IF NOT EXISTS users (
    u_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '用户id（自增主键）',
    username VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名（唯一，用于登录）',
    password VARCHAR(100) NOT NULL COMMENT '密码（生产环境建议用bcrypt/MD5加密存储，示例：MD5("123456")）',
    name VARCHAR(50) NOT NULL COMMENT '用户真实姓名',
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user' COMMENT '用户角色：admin-管理员，user-普通用户',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间（自动生成）',
    user_status TINYINT NOT NULL DEFAULT 1 COMMENT '用户状态：1-启用，2-暂停使用，3-未启用'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户信息表';

-- 3. 创建classes表（班级表）- 核心新增：自定义班级ID作为业务主键
CREATE TABLE IF NOT EXISTS classes (
    class_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '班级自增id（内部主键）',
    class_code VARCHAR(20) NOT NULL UNIQUE COMMENT '自定义班级ID（必填，如jsj110、wy202301）',
    class_name VARCHAR(100) NOT NULL UNIQUE COMMENT '班级名称（唯一，如：2023级计算机1班）',
    class_year ENUM('2023', '2024', '2025') NOT NULL COMMENT '班级年份：仅支持2023、2024、2025',
    class_status TINYINT NOT NULL DEFAULT 3 COMMENT '班级状态：1-启用，2-暂停使用，3-未启用',
    INDEX idx_class_code (class_code) -- 新增：自定义班级ID索引，提升关联查询效率
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='班级信息表';

-- 4. 创建student表（学生表）- 核心调整：关联自定义班级ID + 新增备注字段
CREATE TABLE IF NOT EXISTS student (
    s_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '学生id（自增主键）',
    s_name VARCHAR(50) NOT NULL COMMENT '学生姓名',
    s_class_code VARCHAR(20) NOT NULL COMMENT '关联自定义班级ID（对应classes表的class_code）',
    s_num VARCHAR(20) COMMENT '学生学号（取消唯一约束，允许为空，业务层控制唯一性）',
    tags VARCHAR(200) COMMENT '学生标签（如：优秀、进步，多个标签用逗号分隔）',
    student_status TINYINT NOT NULL DEFAULT 1 COMMENT '学生状态：1-启用（在校），2-休学，3-毕业',
    s_remark TEXT COMMENT '学生备注（非必填，如：家庭情况、特殊说明等）',
    -- 外键约束：关联自定义班级ID（替代原class_id）
    FOREIGN KEY (s_class_code) REFERENCES classes(class_code) 
        ON DELETE RESTRICT  -- 禁止删除被学生关联的班级
        ON UPDATE CASCADE   -- 班级自定义ID更新时，学生表同步更新
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='学生信息表';

-- 优化索引：针对自定义班级ID创建索引，提升关联查询效率
CREATE INDEX idx_student_class_code ON student(s_class_code);
-- 新增：学生姓名索引，方便按姓名查询
CREATE INDEX idx_student_name ON student(s_name);

-- 5. 创建color_config表（状态颜色配置表）- 微调：补充状态值说明
CREATE TABLE IF NOT EXISTS color_config (
    c_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '颜色配置id（自增主键）',
    c_name VARCHAR(50) NOT NULL COMMENT '状态名称（如：未完成、已完成、逾期）',
    c_color CHAR(7) NOT NULL COMMENT '十六进制颜色（如：#FF0000，需包含#）',
    status_id TINYINT NOT NULL UNIQUE COMMENT '状态id（唯一，对应records表的status_id，取值1-5）',
    INDEX idx_status_id (status_id) -- 新增：状态ID索引，提升关联效率
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='状态与颜色映射配置表';

-- 6. 创建tasks表（任务表）- 优化：补充字段注释 + 新增修改时间触发器
CREATE TABLE IF NOT EXISTS tasks (
    task_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '任务id（自增主键）',
    task_title VARCHAR(200) NOT NULL COMMENT '任务标题（必填，如：2023级期中考试安排）',
    task_des TEXT COMMENT '任务描述（非必填，详细说明任务内容）',
    task_json JSON NOT NULL COMMENT '任务详细配置（JSON格式，如：{"class_code":["jsj110","wy202301"],"u_id":[1,2]}）',
    task_status TINYINT NOT NULL DEFAULT 2 COMMENT '任务状态：1-激活，2-未开始，3-已暂停，4-已结束',
    task_by_user INT NOT NULL COMMENT '创建用户id（对应users表的u_id）',
    task_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '任务创建时间（自动生成）',
    task_updated DATETIME COMMENT '任务修改时间（自动更新）',
    -- 外键约束：确保创建任务的用户存在
    FOREIGN KEY (task_by_user) REFERENCES users(u_id)
        ON DELETE RESTRICT  -- 禁止删除创建了任务的用户
        ON UPDATE CASCADE   -- 用户id更新时，任务表同步更新
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务信息表';

-- 优化索引：新增任务状态索引，方便按状态筛选任务
CREATE INDEX idx_tasks_createuser ON tasks(task_by_user);
CREATE INDEX idx_tasks_status ON tasks(task_status);

-- 新增触发器：自动更新task_updated字段（替代手动更新）
DELIMITER //
CREATE TRIGGER trg_tasks_updated BEFORE UPDATE ON tasks
FOR EACH ROW
BEGIN
    SET NEW.task_updated = CURRENT_TIMESTAMP;
END //
DELIMITER ;

-- 7. 创建records表（任务记录/提交记录表）- 优化：字段注释 + 索引调整
CREATE TABLE IF NOT EXISTS records (
    rec_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '记录id（自增主键）',
    task_id INT NOT NULL COMMENT '关联任务id（对应tasks表的task_id）',
    student_id INT NOT NULL COMMENT '关联学生id（对应student表的s_id）',
    status_id TINYINT NOT NULL COMMENT '状态id（对应color_config表的status_id，取值1-5）',
    remarks TEXT COMMENT '备注（如：任务延迟原因、特殊说明）',
    updated_by INT NOT NULL COMMENT '操作人id（对应users表的u_id，谁修改了这条记录）',
    rec_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '记录创建时间（自动生成）',
    rec_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '记录修改时间（自动更新）',
    -- 外键约束：确保关联数据完整性
    FOREIGN KEY (task_id) REFERENCES tasks(task_id)
        ON DELETE CASCADE   -- 任务删除时，关联记录同步删除
        ON UPDATE CASCADE,
    FOREIGN KEY (student_id) REFERENCES student(s_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(u_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    FOREIGN KEY (status_id) REFERENCES color_config(status_id) -- 新增：关联状态颜色表，确保状态合法
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    -- 联合唯一约束：同一学生对同一任务只能有一条记录
    UNIQUE KEY uk_task_student (task_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务记录/学生提交记录表';

-- 优化索引：复合索引覆盖常用查询场景
CREATE INDEX idx_records_task_student ON records(task_id, student_id);
CREATE INDEX idx_records_status ON records(status_id);
CREATE INDEX idx_records_updated_by ON records(updated_by); -- 新增：操作人索引

-- 8. 创建logs表（操作日志表）- 核心修复：外键冲突 + 移除IP字段
CREATE TABLE IF NOT EXISTS logs (
    l_id INT PRIMARY KEY AUTO_INCREMENT COMMENT '日志id（自增主键）',
    u_id INT COMMENT '关联用户id（对应users表的u_id，谁执行了操作）', -- 移除NOT NULL，适配ON DELETE SET NULL
    action TEXT NOT NULL COMMENT '操作类型/描述（如：登录、退出、新增学生、修改任务状态）',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间（自动生成）',
    -- 外键约束：用户删除时日志保留，u_id设为NULL
    FOREIGN KEY (u_id) REFERENCES users(u_id)
        ON DELETE SET NULL  
        ON UPDATE CASCADE   
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户操作日志表';

-- 优化索引：日志表按用户+时间查询，新增复合索引
CREATE INDEX idx_logs_user ON logs(u_id);
CREATE INDEX idx_logs_created_at ON logs(created_at);
CREATE INDEX idx_logs_user_time ON logs(u_id, created_at); -- 复合索引：提升按用户+时间筛选效率