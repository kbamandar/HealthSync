output "alb_dns_name" {
  value = aws_lb.this.dns_name
}

output "cluster_name" {
  value = aws_ecs_cluster.this.name
}

output "task_security_group_id" {
  value = aws_security_group.tasks.id
}
