-- Миграция для добавления полей priority и completed в таблицу tasks
-- Выполните этот SQL скрипт, если база данных уже создана

USE task_planner;

-- Добавляем поле приоритета (1=Критическая, 2=Средней важности, 3=По мере возможности)
ALTER TABLE tasks 
ADD COLUMN priority TINYINT DEFAULT 2 COMMENT '1=Критическая, 2=Средней важности, 3=По мере возможности';

-- Добавляем поле статуса выполнения
ALTER TABLE tasks 
ADD COLUMN completed TINYINT(1) DEFAULT 0 COMMENT '0=Не выполнена, 1=Выполнена';

-- Добавляем индексы для быстрой сортировки
ALTER TABLE tasks 
ADD INDEX idx_priority (priority),
ADD INDEX idx_completed (completed);

