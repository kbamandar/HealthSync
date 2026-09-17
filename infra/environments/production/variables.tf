variable "aws_region" {
  description = "India data residency — no PHI leaves ap-south-1 (Mumbai)"
  type        = string
  default     = "ap-south-1"
}

variable "azs" {
  type    = list(string)
  default = ["ap-south-1a", "ap-south-1b"]
}

variable "db_master_password" {
  description = "Set via TF_VAR_db_master_password from Secrets Manager, never committed"
  type        = string
  sensitive   = true
}

variable "container_image" {
  description = "ECR image URI built by CI, e.g. <account>.dkr.ecr.ap-south-1.amazonaws.com/api:<sha>"
  type        = string
}

variable "acm_certificate_arn" {
  type = string
}
