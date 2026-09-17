variable "name" {
  type = string
}

variable "secret_names" {
  description = "Logical secret names, e.g. [\"db-password\", \"app-key\", \"msg91-api-key\"]. Values are set out-of-band via the AWS console/CLI, never in Terraform state."
  type        = list(string)
}

variable "tags" {
  type    = map(string)
  default = {}
}
