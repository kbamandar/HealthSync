variable "name" {
  description = "Name prefix for RDS resources"
  type        = string
}

variable "vpc_id" {
  type = string
}

variable "private_subnet_ids" {
  description = "Private subnets only — no public RDS endpoint (security checklist §11)"
  type        = list(string)
}

variable "allowed_security_group_ids" {
  description = "Security groups allowed to connect to Postgres (e.g. ECS task SG)"
  type        = list(string)
}

variable "instance_class" {
  type    = string
  default = "db.t3.small"
}

variable "allocated_storage_gb" {
  type    = number
  default = 20
}

variable "engine_version" {
  type    = string
  default = "16"
}

variable "db_name" {
  type    = string
  default = "healthsync"
}

variable "master_username" {
  type    = string
  default = "healthsync"
}

variable "master_password" {
  description = "Pulled from AWS Secrets Manager, never set inline in tfvars"
  type        = string
  sensitive   = true
}

variable "multi_az" {
  type    = bool
  default = false
}

variable "backup_retention_days" {
  type    = number
  default = 7
}

variable "tags" {
  type    = map(string)
  default = {}
}
