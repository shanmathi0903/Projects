package beanFolder;

public class OperatorsBus{
	private int operatorId;
	private String busName;
	private String regNo;
	
	public OperatorsBus(int id,String busName,String regNo){
		this.operatorId=id;
		this.busName=busName;
		this.regNo=regNo;
	}
	
	public int getOperatorId(){
		return operatorId;
	}
	public String getBusName(){
		return busName;
	}
	public String getRegNo(){
		return regNo;
	}
}