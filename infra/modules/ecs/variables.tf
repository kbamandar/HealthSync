variable "name" {
  type = string
}

variable "vpc_id" {
  type = string
}

variable "public_subnet_ids" {
  description = "For the ALB"
  type        = list(string)
}

variable "private_subnet_ids" {
  description = "For the ECS tasks"
  type        = list(string)
}

variable "container_image" {
  description = "Full ECR image URI, e.g. <account>.dkr.ecr.ap-south-1.amazonaws.com/api:latest"
  type        = string
}

variable "container_port" {
  type    = number
  default = 8000
}

variable "desired_count" {
  type    = number
  default = 1
}

variable "cpu" {
  type    = number
  default = 512
}

variable "memory" {
  type    = number
  default = 1024
}

variable "environment_variables" {
  description = "Non-secret env vars for the container"
  type        = map(string)
  default     = {}
}

variable "secrets" {
  description = "Map of env var name to Secrets Manager ARN"
  type        = map(string)
  default     = {}
}

variable "health_check_path" {
  type    = string
  default = "/up"
}

variable "acm_certificate_arn" {
  description = "ACM cert for the HTTPS listener — provisioned once a domain exists"
  type        = string
}

variable "tags" {
  type    = map(string)
  default = {}
}
