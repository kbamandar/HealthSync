locals {
  name = "healthsync-production"
  tags = {
    Project     = "HealthSync"
    Environment = "production"
  }
}

module "vpc" {
  source = "../../modules/vpc"

  name                 = local.name
  azs                  = var.azs
  public_subnet_cidrs  = ["10.1.0.0/24", "10.1.1.0/24"]
  private_subnet_cidrs = ["10.1.10.0/24", "10.1.11.0/24"]
  tags                 = local.tags
}

module "ecr" {
  source = "../../modules/ecr"

  repository_names = ["api"]
  tags             = local.tags
}

module "secrets" {
  source = "../../modules/secrets"

  name         = local.name
  secret_names = ["db-password", "app-key", "msg91-api-key", "gupshup-api-key"]
  tags         = local.tags
}

module "ecs" {
  source = "../../modules/ecs"

  name                = local.name
  vpc_id              = module.vpc.vpc_id
  public_subnet_ids   = module.vpc.public_subnet_ids
  private_subnet_ids  = module.vpc.private_subnet_ids
  container_image     = var.container_image
  desired_count       = 2
  cpu                 = 1024
  memory              = 2048
  acm_certificate_arn = var.acm_certificate_arn
  tags                = local.tags
}

module "rds" {
  source = "../../modules/rds"

  name                       = local.name
  vpc_id                     = module.vpc.vpc_id
  private_subnet_ids         = module.vpc.private_subnet_ids
  allowed_security_group_ids = [module.ecs.task_security_group_id]
  instance_class             = "db.t3.medium"
  multi_az                   = true
  backup_retention_days      = 30
  master_password            = var.db_master_password
  tags                       = local.tags
}

module "redis" {
  source = "../../modules/redis"

  name                       = local.name
  vpc_id                     = module.vpc.vpc_id
  private_subnet_ids         = module.vpc.private_subnet_ids
  allowed_security_group_ids = [module.ecs.task_security_group_id]
  node_type                  = "cache.t3.small"
  tags                       = local.tags
}

# CloudFront, AWS WAF (attached to the ALB), and GuardDuty are §11 launch-
# blocking items added once a real domain/cert and traffic patterns exist —
# left as a TODO rather than guessed at here.
